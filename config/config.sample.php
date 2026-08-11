<?php
/**
 * Soma Cashflow - Local configuration
 *
 * Copy this file to config.php and fill in your local XAMPP/MariaDB
 * credentials. config.php is gitignored so real credentials never
 * get committed.
 */

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'soma_cashflow',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // Set to true while developing locally to show detailed errors
    'debug' => true,
];
