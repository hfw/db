<?php

// strict everything
error_reporting(E_ALL);
ini_set('assert.exception', 1);
set_error_handler(function($code, $message, $file, $line) {
    throw new ErrorException($message, $code, 1, $file, $line);
});

// scan for and include the autoloader
$dir = __DIR__;
while ($dir = dirname($dir) and $dir !== '.') {
    if (file_exists("{$dir}/vendor/autoload.php")) {
        include_once "{$dir}/vendor/autoload.php";
        break;
    }
}
