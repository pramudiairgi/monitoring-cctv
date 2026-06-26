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

    /*
    |--------------------------------------------------------------------------
    | HLS Proxy
    |--------------------------------------------------------------------------
    |
    | When using nginx reverse proxy to bypass browser 6-connection limit,
    | set HLS_PROXY_PREFIX=/hls and HLS_PROXY_DOMAINS in .env.
    |
    | Example:
    |   HLS_PROXY_PREFIX=/hls
    |   HLS_PROXY_DOMAINS="https://livepantau.semarangkota.go.id"
    |
    */
    'hls' => [
        'proxy_prefix' => env('HLS_PROXY_PREFIX'),
        'proxy_domains' => explode(',', env('HLS_PROXY_DOMAINS', '')),
    ],

];
