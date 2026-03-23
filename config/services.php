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
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bfl' => [
        'base_url' => env('BFL_BASE_URL', 'https://api.bfl.ai'),
        'model' => env('BFL_MODEL', 'flux-2-pro-preview'),
        'key' => env('BFL_API_KEY'),
        'output_format' => env('BFL_OUTPUT_FORMAT', 'jpeg'),
        'poll_interval_ms' => env('BFL_POLL_INTERVAL_MS', 500),
        'max_poll_attempts' => env('BFL_MAX_POLL_ATTEMPTS', 30),
    ],

];
