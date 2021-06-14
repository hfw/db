#!/usr/bin/php
<?php
error_reporting(E_ALL);
ini_set('assert.exception', 1);
set_error_handler(function($code, $message, $file, $line) {
    throw new ErrorException($message, $code, 1, $file, $line);
});

// scan for and include the autoloader
$root = __DIR__;
while ($root !== '.' and !file_exists("{$root}/vendor/autoload.php")) {
    $root = dirname($root);
}
include_once "{$root}/vendor/autoload.php";

use Helix\DB;
use Helix\DB\Junction;
use Helix\DB\MigrationInterface;
use Helix\DB\Record;
use Helix\DB\Schema;

// parse cli opts
$opt = getopt('h', [
    'config:',
    'connection:',
    'down::',
    'help',
    'junction:',
    'record:',
    'status',
    'up::',
]);

// stdout helpers
$stdout = fn($string) => print("{$string}\n\n");
$stderr = fn($string) => fputs(STDERR, "{$string}\n\n");

// usage info
$usage = function() use ($argv, $stderr) {
    return $stderr(<<< USAGE

    $ php {$argv[0]} [OPTIONS] ACTION

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
};

// missing action, or help requested
if (!$opt or isset($opt['h']) or isset($opt['help'])) {
    $usage();
    exit(1);
}

// load db and migrator
$db = DB::fromConfig($opt['connection'] ?? 'default', $opt['config'] ?? 'db.config.php');
$realLogger = $db->getLogger();
$db->setLogger(fn($sql) => $stdout($sql) and $realLogger($sql));
$TRANSACTION = $db->newTransaction();
$migrator = $db->getMigrator();
$dir = $migrator->getDir();
$current = $migrator->getCurrent() ?: 'NONE';

switch (true) {

    case isset($opt['down']):
        if ($to = $opt['down'] ?: null) {
            $stdout("-- Downgrading from \"{$current}\" to \"{$to}\" ...");
        }
        else {
            $stdout("-- Downgrading once from \"{$current}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->down($to ?: null)) {
            $stderr("-- Nothing to do.");
            return;
        }
        $TRANSACTION->commit();
        return;

    case isset($opt['record']) or isset($opt['junction']):
        $class = str_replace('/', '\\', $opt['record'] ?? $opt['junction']);
        if (!$class) {
            $usage();
            exit(1);
        }
        $access = isset($opt['record']) ? $db->getRecord($class) : $db->getJunction($class);
        $sequence = gmdate(DATE_ATOM) . '_' . $class;
        $file = "{$dir}/{$sequence}.php";
        $stdout("-- Preparing {$file}");
        sleep(1); // prevent fast CLI from clobbering sequences with identical times
        is_dir($dir) or mkdir($dir, 0775, true);

        // method bodies, each an operation, each will be indented twice.
        $use = [
            Schema::class,
            MigrationInterface::class,
        ];
        $up = []; // up()
        $down = []; // down()
        $addUp = function(string $operation) use (&$up) {
            return array_push($up, str_replace("\t", '    ', $operation));
        };
        $addDown = function(string $operation) use (&$down) {
            return array_unshift($down, str_replace("\t", '    ', $operation));
        };
        if ($access instanceof Record) {
            // create table
            if (!$db[$access->getName()]) {
                $columns = [];
                foreach ($access->getTypes() as $property => $type) {
                    $T_CONST = Schema::PHP_TYPE_NAMES[$type];
                    $columns[$property] = "'{$property}' => Schema::{$T_CONST}";
                    if (!$access->isNullable($property)) {
                        $columns[$property] .= '_STRICT';
                    }
                }
                $columns['id'] = "'id' => Schema::T_AUTOINCREMENT";
                $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
                $addUp("\$schema->createTable('{$access}', {$columns});");
                $addDown("\$schema->dropTable('{$access}');");
            }

            // check each eav
            foreach ($access->getEav() as $eav) {
                // create table
                if (!$db[$eav->getName()]) {
                    $T_CONST = Schema::PHP_TYPE_NAMES[$eav->getType()];
                    $columns = [
                        "'entity' => Schema::T_INT_STRICT",
                        "'attribute' => Schema::T_STRING_STRICT",
                        "'value' => Schema::{$T_CONST}"
                    ];
                    $columns = "[\n\t\t\t" . implode(",\n\t\t\t", $columns) . "\n\t\t]";
                    $constraints = [
                        "Schema::TABLE_PRIMARY => ['entity', 'attribute']",
                        "Schema::TABLE_FOREIGN => \$schema['{$access}']['id']"
                    ];
                    $constraints = "[\n\t\t\t" . implode(",\n\t\t\t", $constraints) . "\n\t\t]";
                    $addUp("\$schema->createTable('{$eav}', {$columns}, {$constraints});");
                    $addDown("\$schema->dropTable('{$eav}');");
                }
            }
        }
        elseif ($access instanceof Junction) {
            // create table
            if (!$db[$access->getName()]) {
                $records = $access->getRecords();
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
                $addUp("\$schema->createTable('{$access}', {$columns}, {$constraints});");
                $addDown("\$schema->dropTable('{$access}');");
            }
        }
        else {
            $stderr("Unrecognized access for \"{$class}\"");
            exit(1);
        }
        sort($use);
        $use = array_map(fn(string $class) => "use {$class};", array_unique($use));
        $use = implode("\n", $use);
        if (!$up or !$down) {
            $stderr("-- Nothing to do.");
            return;
        }
        $up = "\t\t" . implode("\n\t\t", $up);
        $down = "\t\t" . implode("\n\t\t", $down);
        $stderr("-- Writing {$file}");
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
        return;

    case isset($opt['status']):
        $stdout("-- Current Migration State: \"{$current}\"");
        return;

    case isset($opt['up']):
        if ($to = $opt['up'] ?: null) {
            $stdout("-- Upgrading from \"{$current}\" to \"{$to}\" ...");
        }
        else {
            $stdout("-- Upgrading ALL starting from \"{$current}\" ...");
        }
        sleep(3); // time to cancel
        if ($current === $migrator->up($to ?: null)) {
            $stderr("-- Nothing to do.");
            return;
        }
        $TRANSACTION->commit();
        return;

    default:
        $usage();
        exit(1);
}