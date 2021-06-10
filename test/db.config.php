<?php

/**
 * Ideally you should not commit your database config.
 * This is here for illustrative and testing purposes.
 *
 * Each entry contains the construction arguments, keyed by the connection's name.
 */
return [
    'default' => [                  // "default" connection
        'dsn' => 'sqlite:test.db',  // required
        'username' => null,         // optional
        'password' => null,         // optional
        'options' => []             // optional
    ]
];