#!/usr/bin/php
<?php

// scan for and include the autoloader
$root = __DIR__;
while ($root !== '.' and !file_exists("{$root}/vendor/autoload.php")) {
    $root = dirname($root);
}
include_once "{$root}/vendor/autoload.php";

use Helix\DB;

// parse cli opts
$opt = getopt('h', [
    'config:',
    'connection:',
    'help',
    'to:'
]);

// usage info
$usage = function() use ($argv) {
    fputs(STDERR, <<< USAGE
    
    $ php {$argv[0]} ACTION [OPTIONS]
    
    ACTION:
    
        up
        down
        status
    
    OPTIONS:
    
        --help, -h
            Prints usage information to STDERR and calls exit(1)
    
        --config=db.config.php
            Chooses the configuration file.
            
        --connection=default
            Chooses the connection from the configuration file.
        
        --to=
            Chooses the final migration sequence identifier.
            When omitted or empty, the target is NULL.
            For upgrades, NULL means all are performed.
            For downgrades, NULL means only one is performed. 


    USAGE
    );
    return 1; // given to exit()
};

// missing action, or help requested
if (!isset($argv[1]) or isset($opt['h']) or isset($opt['help'])) {
    exit($usage());
}

// stdout helper
$stdout = fn($string) => print("{$string}\n\n");

// load db and migrator
$db = DB::fromConfig($opt['connection'] ?? 'default', $opt['config'] ?? 'db.config.php');
$realLogger = $db->getLogger();
$db->setLogger(fn($sql) => $stdout($sql) and $realLogger($sql));
$migrator = $db->getMigrator();

// begin action
$current = $migrator->getCurrent() ?: 'NONE';
$stdout("Current Migration State: \"{$current}\"");
$to = $opt['to'] ?? null;
switch ($argv[1]) {
    case 'down':
        if ($to) {
            $stdout("Downgrading from \"{$current}\" to \"{$to}\" ...");
        }
        else {
            $stdout("Downgrading once from \"{$current}\" ...");
        }
        sleep(3); // time to cancel
        $current = $migrator->down($to ?: null);
        break;
    case 'status':
        return;
    case 'up':
        if ($to) {
            $stdout("Upgrading from \"{$current}\" to \"{$to}\" ...");
        }
        else {
            $stdout("Upgrading ALL starting from \"{$current}\" ...");
        }
        sleep(3); // time to cancel
        $current = $migrator->up($to ?: null);
        break;
    default:
        exit($usage());
}

$current = $current ?: 'NONE';
$stdout("Current Migration State: \"{$current}\"");
