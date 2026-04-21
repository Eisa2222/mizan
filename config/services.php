<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'elasticsearch' => [
        'host'  => env('ELASTICSEARCH_HOST', 'http://localhost:9200'),
        'index' => env('ELASTICSEARCH_INDEX', 'mizaan_legal'),
    ],

    // AI backend: set AI_PROVIDER to 'ollama' (free, local) or 'anthropic' (cloud, paid).
    // Default: ollama — runs on your machine with no API key needed.
    'ai' => [
        'provider'     => env('AI_PROVIDER', 'ollama'),
        'ollama_url'   => env('OLLAMA_URL', 'http://localhost:11434'),
        'ollama_model' => env('OLLAMA_MODEL', 'llama3'),
    ],

    'anthropic' => [
        'api_key'  => env('ANTHROPIC_API_KEY'),
        'model'    => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'version'  => env('ANTHROPIC_VERSION', '2023-06-01'),
    ],

    // Moyasar payment gateway (Saudi). Actual values are written live by
    // ApplySystemSettings middleware from the `system_settings` table —
    // the env fallbacks exist only so CI can spin up without pre-seeding.
    'moyasar' => [
        'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
        'secret_key'      => env('MOYASAR_SECRET_KEY'),
        'webhook_secret'  => env('MOYASAR_WEBHOOK_SECRET'),
        'test_mode'       => env('MOYASAR_TEST_MODE', true),
        'base_url'        => 'https://api.moyasar.com/v1',
    ],

];
