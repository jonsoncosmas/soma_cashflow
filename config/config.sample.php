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
    // AI transaction categorization (Phase 6): tries your OpenRouter free
    // models first (in the order listed - cheapest/free first), then
    // OpenAI, then falls back to Claude. Leave a key blank to skip that
    // provider. Get keys from:
    //   OpenRouter: https://openrouter.ai/settings/keys (free tier, no card needed)
    //   OpenAI:     https://platform.openai.com/api-keys
    //   Anthropic:  https://console.anthropic.com/settings/keys
    // OpenRouter's free model list changes often - check current options at
    // https://openrouter.ai/models?max_price=0 and copy the exact model ID
    // (it ends in ":free"). List as many as you want tried in order; if the
    // first is rate-limited or down, the next one is tried automatically.
    'ai' => [
        'openrouter_api_key' => '',
        'openrouter_models'  => [
            // 'nvidia/nemotron-3-ultra-550b-a55b:free',
            // 'thinkingmachines/inkling:free',
            // 'dots-studio/dots-3-note-preview:free',
            // 'nvidia/nemotron-3-super-120b-a12b:free',
            // 'poolside/laguna-s-2.1:free',
            // 'inclusionai/ling-3.0-flash-vl:free',
            // 'nex-agi/nex-n2.5-pro:free',
            // 'thinkingmachines/inkling-small:free',
            // 'nvidia/nemotron-3.5-lightning:free',
            // 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
        ],
        'openai_api_key'    => '',
        'anthropic_api_key' => '',
    ],
];
