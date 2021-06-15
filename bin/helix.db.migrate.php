#!/usr/bin/php
<?php
include_once __DIR__ . "/.init.php";

use Helix\DB;
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

    public function __construct (array $argv, array $opt) {
        $this->argv = $argv;
        $opt['connection'] ??= 'default';
        $opt['config'] ??= 'db.config.php';
        $this->opt = $opt;
        $this->db = DB::fromConfig($opt['connection'], $opt['config']);
        $realLogger = $this->db->getLogger();
        $this->db->setLogger(fn($sql) => $this->_stdout($sql) and $realLogger($sql));
    }

    private function _stderr (string $text): void {
        fputs(STDERR, "{$text}\n\n");
    }

    private function _stdout (string $text): void {
        echo "{$text}\n\n";
    }

    private function _usage_exit (): void {
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
    public function _exec (): void {
        foreach (['h', 'help', 'status', 'up', 'down', 'record', 'junction'] as $action) {
            if (isset($this->opt[$action])) {
                $this->{$action}($this->opt[$action] ?: null);
                return;
            }
        }
        $this->_usage_exit();
    }

    private function h (): void {
        $this->_usage_exit();
    }

    private function help (): void {
        $this->_usage_exit();
    }

    private function status (): void {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent() ?? 'NONE';
        $this->_stdout("-- Current Migration State: {$current}");
        unset($transaction);
    }

    private function up (?string $to): void {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent();
        $currentString = $current ?: 'NONE';
        if ($to) {
            $this->_stdout("-- Upgrading from \"{$currentString}\" to \"{$to}\" ...");
        }
        else {
            $this->_stdout("-- Upgrading ALL starting from \"{$currentString}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->up($to ?: null)) {
            $this->_stdout("-- Nothing to do.");
        }
        else {
            $transaction->commit();
        }
    }

    private function down (?string $to): void {
        $migrator = $this->db->getMigrator();
        $transaction = $this->db->newTransaction();
        $current = $migrator->getCurrent();
        $currentString = $current ?: 'NONE';
        if ($to) {
            $this->_stdout("-- Downgrading from \"{$currentString}\" to \"{$to}\" ...");
        }
        else {
            $this->_stdout("-- Downgrading once from \"{$currentString}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->down($to ?: null)) {
            $this->_stdout("-- Nothing to do.");
        }
        else {
            $transaction->commit();
        }
    }

    private function _toClass (string $path): string {
        return str_replace('/', '\\', $path);
    }

    private function record (string $class): void {
        $class = $this->_toClass($class) or $this->_usage_exit();
        $record = $this->db->getRecord($class);
        $use = [];
        $up = [];
        $down = [];

        // create table
        if (!$this->db[$record->getName()]) {
            $columns = [];
            foreach ($record->getTypes() as $property => $type) {
                $T_CONST = Schema::PHP_TYPE_NAMES[$type];
                $columns[$property] = "'{$property}' => Schema::{$T_CONST}";
                if (!$record->isNullable($property)) {
                    $columns[$property] .= '_STRICT';
                }
            }
            $columns['id'] = "'id' => Schema::T_AUTOINCREMENT";
            $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
            $up[] = "\$schema->createTable('{$record}', {$columns});";
            $down[] = "\$schema->dropTable('{$record}');";
        }

        // check each eav
        foreach ($record->getEav() as $eav) {
            // create table
            if (!$this->db[$eav->getName()]) {
                $T_CONST = Schema::PHP_TYPE_NAMES[$eav->getType()];
                $columns = [
                    "'entity' => Schema::T_INT_STRICT",
                    "'attribute' => Schema::T_STRING_STRICT",
                    "'value' => Schema::{$T_CONST}"
                ];
                $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
                $constraints = [
                    "Schema::TABLE_PRIMARY => ['entity', 'attribute']",
                    "Schema::TABLE_FOREIGN => \$schema['{$record}']['id']"
                ];
                $constraints = "[\n\t\t\t" . implode(",\n\t\t\t", $constraints) . "\n\t\t]";
                $up[] = "\$schema->createTable('{$eav}', {$columns}, {$constraints});";
                $down[] = "\$schema->dropTable('{$eav}');";
            }
        }

        $this->write($class, $use, $up, $down);
    }

    private function junction (string $class): void {
        $class = $this->_toClass($class) or $this->_usage_exit();
        $junction = $this->db->getJunction($class);
        $use = [];
        $up = [];
        $down = [];

        // create table
        if (!$this->db[$junction->getName()]) {
            $records = $junction->getRecords();
            $columns = array_map(
                fn(string $column) => "'{$column}' => Schema::T_INT_STRICT",
                array_keys($records)
            );
            $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
            $primary = array_map(fn(string $column) => "'{$column}'", array_keys($records));
            $primary = "[" . implode(', ', $primary) . "]";
            $foreign = array_map(
                fn(string $column, Record $record) => "'{$column}' => \$schema['{$record}']['id']",
                array_keys($records),
                $records
            );
            $foreign = "[\n\t\t\t\t" . implode(",\n\t\t\t\t", $foreign) . "\n\t\t\t]";
            $constraints = [
                "Schema::TABLE_PRIMARY => {$primary}",
                "Schema::TABLE_FOREIGN => {$foreign}"
            ];
            $constraints = "[\n\t\t\t" . implode(",\n\t\t\t", $constraints) . "\n\t\t]";
            $up[] = "\$schema->createTable('{$junction}', {$columns}, {$constraints});";
            $down[] = "\$schema->dropTable('{$junction}');";
        }

        $this->write($class, $use, $up, $down);
    }

    private function write (string $class, array $use, array $up, array $down): void {
        if (!$up or !$down) {
            $this->_stdout("-- Nothing to do.");
            return;
        }

        // convert $use to imports
        $use[] = Schema::class;
        $use[] = MigrationInterface::class;
        sort($use);
        $use = array_map(fn(string $import) => "use {$import};", array_unique($use));

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
             * @var Schema \$schema
             */
            public function up (\$schema)
            {
        {$up}
            }

            /**
             * @var Schema \$schema
             */
            public function down (\$schema)
            {
        {$down}
            }

        };

        MIGRATION
        );
    }

})->_exec();
