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

    // AI transaction categorization (Phase 6): OpenAI is tried first, then
    // Claude as a fallback if OpenAI fails or isn't configured. Leave a key
    // blank to skip that provider entirely. Get keys from:
    //   OpenAI:    https://platform.openai.com/api-keys
    //   Anthropic: https://console.anthropic.com/settings/keys
    // These are never sent anywhere except directly to that provider's API.
    'ai' => [
        'openai_api_key'    => '',
        'anthropic_api_key' => '',
    ],
];
