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

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'telr' => [
        'store_id' => env('TELR_STORE_ID'),
        'auth_key' => env('TELR_AUTH_KEY'),
        'test' => env('TELR_TEST_MODE', true),
    ],

    'tabby' => [
        'public_key' => env('TABBY_PUBLIC_KEY'),
        'secret_key' => env('TABBY_SECRET_KEY'),
        'merchant_code' => env('TABBY_MERCHANT_CODE'),
    ],

    'tamara' => [
        'token' => env('TAMARA_API_TOKEN'),
        'base_url' => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),
        'notification_token' => env('TAMARA_NOTIFICATION_TOKEN'),
    ],

    // Simulated card page for demos; never enable on the live shop.
    'mock_payments' => env('MOCK_PAYMENTS', false),

];
