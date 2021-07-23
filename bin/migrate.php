#!/usr/bin/php
<?php

namespace Helix;

include_once __DIR__ . "/.init.php";

use DateTime;
use Helix\DB\MigrationInterface;
use Helix\DB\Record;
use Helix\DB\Schema;

$opt = getopt('h', [
    'config:',
    'connection:',
    'help',
    'status',
    'up::',
    'down::',
    'record:',
    'junction:',
]);

(new class ($argv, $opt) {

    private array $argv;

    private array $opt;

    private DB $db;

    private Schema $schema;

    public function __construct(array $argv, array $opt)
    {
        $this->argv = $argv;
        $opt['connection'] ??= 'default';
        $opt['config'] ??= 'db.config.php';
        $this->opt = $opt;
        $this->db = DB::fromConfig($opt['connection'], $opt['config']);
        $realLogger = $this->db->getLogger();
        $this->db->setLogger(fn($sql) => $this->_stdout($sql) or $realLogger($sql));
        $this->schema = $this->db->getSchema();
    }

    private function _stderr(string $text): void
    {
        fputs(STDERR, "{$text}\n\n");
    }

    private function _stdout(string $text): void
    {
        echo "{$text}\n\n";
    }

    private function _usage_exit(): void
    {
        $this->_stderr(<<< USAGE

        $ php {$this->argv[0]} [OPTIONS] ACTION

        OPTIONS:

            --config=db.config.php

                Chooses the configuration file.

            --connection=default

                Chooses the connection from the configuration file.

        ACTIONS:

            -h
            --help

                Prints this usage information to STDERR and calls exit(1)

            --status

                Outputs the current migration sequence.

            --up=
            --down=

                Migrates up or down, optionally to a target sequence.
                For upgrades, the default target is all the way.
                For downgrades, the default target is the previous sequence.

            --record=<CLASS>
            --junction=<INTERFACE>

                The FQN of an annotated class or interface,
                which the DB instance can use to return Record or Junction access.

                The access object's tables are inspected against the database,
                and appropriate migration is then generated into the migrations
                directory. Statically generated migrations preserve history.

                To make CLI execution easier, forward-slashes in the FQN are
                converted to namespace separators.
        USAGE
        );
        exit(1);
    }

    /**
     * @uses h()
     * @uses help()
     * @uses status()
     * @uses up()
     * @uses down()
     * @uses record()
     * @uses junction()
     */
    public function _exec(): void
    {
        foreach (['h', 'help', 'status', 'up', 'down', 'record', 'junction'] as $action) {
            if (isset($this->opt[$action])) {
                $this->{$action}($this->opt[$action] ?: null);
                return;
            }
        }
        $this->_usage_exit();
    }

    private function h(): void
    {
        $this->_usage_exit();
    }

    private function help(): void
    {
        $this->_usage_exit();
    }

    private function status(): void
    {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent() ?? 'NONE';
        $this->_stdout("-- Current Migration State: {$current}");
        unset($transaction);
    }

    private function up(?string $to): void
    {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent();
        $currentString = $current ?: 'NONE';
        if ($to) {
            $this->_stdout("-- Upgrading from \"{$currentString}\" to \"{$to}\" ...");
        } else {
            $this->_stdout("-- Upgrading ALL starting from \"{$currentString}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->up($to ?: null)) {
            $this->_stdout("-- Nothing to do.");
        } else {
            $transaction->commit();
        }
    }

    private function down(?string $to): void
    {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent();
        $currentString = $current ?: 'NONE';
        if ($to) {
            $this->_stdout("-- Downgrading from \"{$currentString}\" to \"{$to}\" ...");
        } else {
            $this->_stdout("-- Downgrading once from \"{$currentString}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->down($to ?: null)) {
            $this->_stdout("-- Nothing to do.");
        } else {
            $transaction->commit();
        }
    }

    private function _toClass(string $path): string
    {
        return str_replace('/', '\\', $path);
    }

    private function record(string $class): void
    {
        $class = $this->_toClass($class) or $this->_usage_exit();
        $record = $this->db->getRecord($class);
        $up = [];
        $down = [];
        if (!$this->schema->getTable($record)) {
            $this->record_create($record, $up, $down);
        } else {
            $this->record_add_columns($record, $up, $down);
            $this->record_drop_columns($record, $up, $down);
        }
        $this->record_create_eav($record, $up, $down);
        $this->write($class, $up, $down);
    }

    private function record_create_eav(Record $record, &$up, &$down)
    {
        foreach ($record->getEav() as $eav) {
            if (!$this->schema->getTable($eav)) {
                $T_CONST = Schema::T_CONST_NAMES[$eav->getType()];
                $columns = [
                    "'entity' => Schema::T_INT | Schema::I_PRIMARY",
                    "'attribute' => Schema::T_STRING | Schema::I_PRIMARY",
                    "'value' => Schema::{$T_CONST}"
                ];
                $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
                $foreign = "[\n\t\t\t'entity' => \$schema['{$record}']['id']\n\t\t]";
                $up[] = "\$schema->createTable('{$eav}', {$columns}, {$foreign});";
                $down[] = "\$schema->dropTable('{$eav}');";
            }
        }
    }

    private function record_add_columns(Record $record, &$up, &$down)
    {
        $columns = $this->schema->getColumnInfo($record);
        $multiUnique = [];
        $serializer = $record->getSerializer();
        foreach ($serializer->getStorageTypes() as $property => $type) {
            if (!isset($columns[$property])) {
                $T_CONST = Schema::T_CONST_NAMES[$type];
                if ($serializer->isNullable($property)) {
                    $T_CONST .= '_NULL';
                }
                $up[] = "\$schema->addColumn('{$record}', '{$property}', Schema::{$T_CONST});";
                $down[] = "\$schema->dropColumn('{$record}', '{$property}');";
            }
            if ($serializer->isUnique($property)) {
                $up[] = "\$schema->addUniqueKeyConstraint('{$record}', ['{$property}']);";
                $down[] = "\$schema->dropUniqueKeyConstraint('{$record}', ['{$property}']);";
            } elseif ($uniqueGroup = $serializer->getUniqueGroup($property)) {
                $multiUnique[$uniqueGroup][] = $property;
            }
        }
        foreach ($multiUnique as $properties) {
            $properties = "'" . implode("','", $properties) . "'";
            $up[] = "\$schema->addUniqueKeyConstraint('{$record}', [{$properties}]);";
            $down[] = "\$schema->dropUniqueKeyConstraint('{$record}', [{$properties}]);";
        }
    }

    private function record_drop_columns(Record $record, &$up, &$down)
    {
        $columns = $this->schema->getColumnInfo($record);
        foreach ($columns as $column => $info) {
            if (!$record[$column]) {
                $T_CONST = Schema::T_CONST_NAMES[$info['type']];
                if ($info['nullable']) {
                    $T_CONST .= '_NULL';
                }
                $up[] = "\$schema->dropColumn('{$record}', '{$column}');";
                $down[] = "\$schema->addColumn('{$record}', '{$column}', Schema::{$T_CONST});";
            }
        }
    }

    private function record_create(Record $record, &$up, &$down)
    {
        $serializer = $record->getSerializer();
        $columns = [];
        foreach ($serializer->getStorageTypes() as $property => $type) {
            $T_CONST = Schema::T_CONST_NAMES[$type];
            if ($serializer->isNullable($property)) {
                $T_CONST .= '_NULL';
            }
            $columns[$property] = "'{$property}' => Schema::{$T_CONST}";
        }

        $columns['id'] = "'id' => Schema::T_AUTOINCREMENT";
        $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
        $up[] = "\$schema->createTable('{$record}', {$columns});";
        $down[] = "\$schema->dropTable('{$record}');";

        foreach ($serializer->getUnique() as $unique) {
            if (is_array($unique)) {
                $unique = implode("', '", $unique);
            }
            $up[] = "\$schema->addUniqueKeyConstraint('{$record}', ['{$unique}']);";
            $down[] = "\$schema->dropUniqueKeyConstraint('{$record}', ['{$unique}']);";
        }
    }

    private function junction(string $class): void
    {
        $class = $this->_toClass($class) or $this->_usage_exit();
        $junction = $this->db->getJunction($class);
        $up = [];
        $down = [];

        if (!$this->schema->getTable($junction)) {
            $records = $junction->getRecords();
            $columns = array_map(
                fn(string $column) => "'{$column}' => Schema::T_INT | Schema::I_PRIMARY",
                array_keys($records)
            );
            $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
            $foreign = array_map(
                fn(string $column, Record $record) => "'{$column}' => \$schema['{$record}']['id']",
                array_keys($records),
                $records
            );
            $foreign = "[\n\t\t\t" . implode(",\n\t\t\t", $foreign) . "\n\t\t]";
            $up[] = "\$schema->createTable('{$junction}', {$columns}, {$foreign});";
            $down[] = "\$schema->dropTable('{$junction}');";
        }

        $this->write($class, $up, $down);
    }

    private function write(string $class, array $up, array $down): void
    {
        if (!$up or !$down) {
            $this->_stdout("-- Nothing to do.");
            return;
        }

        // import stuff
        $use = array_map(fn($import) => "use {$import};", [
            MigrationInterface::class,
            Schema::class
        ]);

        // reverse the $down operations
        $down = array_reverse($down);

        // write
        $dir = $this->db->getMigrator()->getDir();
        is_dir($dir) or mkdir($dir, 0775, true);
        $date = DateTime::createFromFormat('U.u', microtime(true))->format('Y-m-d\TH:i:s.v\Z');
        $sequence = "{$date}_" . str_replace('\\', '_', $class);
        $file = "{$dir}/{$sequence}.php";
        $use = implode("\n", $use);
        $up = str_replace("\t", '    ', "\t\t" . implode("\n\t\t", $up));
        $down = str_replace("\t", '    ', "\t\t" . implode("\n\t\t", $down));
        $this->_stderr("-- Writing {$file}");
        $fh = fopen($file, 'w');
        fputs($fh, <<<MIGRATION
        <?php

        {$use}

        /** {$sequence} */
        return new class implements MigrationInterface {

            /**
             * @param Schema \$schema
             */
            public function up(\$schema)
            {
        {$up}
            }

            /**
             * @param Schema \$schema
             */
            public function down(\$schema)
            {
        {$down}
            }

        };

        MIGRATION
        );
    }

})->_exec();
