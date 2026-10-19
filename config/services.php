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

    'apple' => [
        'email' => env('ICLOUD_EMAIL'),
        'password' => env('ICLOUD_PASSWORD'),
    ],

    'aura' => [
        'email' => env('AURA_EMAIL'),
        'password' => env('AURA_PASSWORD'),
    ],

    'google' => [
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        'service_account' => env('GOOGLE_SERVICE_ACCOUNT_JSON_PATH'),
    ],

    'microsoft' => [
        'client_id' => env('MS_GRAPH_CLIENT_ID'),
        'tenant_id' => env('MS_GRAPH_TENANT_ID'),
        'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
        'user_id' => env('MS_GRAPH_USER_ID'),
    ],

    'openweathermap' => [
        'key' => env('OPENWEATHER_API_KEY'),
        'lat' => env('LATITUDE'),
        'lon' => env('LONGITUDE'),
    ],

    'nutrislice' => [
        'tenant' => env('NUTRISLICE_TENANT', 'polk-fl'),
        'school' => env('NUTRISLICE_SCHOOL', 'lakeland-montessori'),
        'school_name' => env('NUTRISLICE_SCHOOL_NAME', 'Lakeland Montessori'),
        'cache_ttl_seconds' => env('NUTRISLICE_CACHE_TTL_SECONDS', 900),
    ],

    'paprika' => [
        'email' => env('PAPRIKA_EMAIL'),
        'password' => env('PAPRIKA_PASSWORD'),
        'menu_plan_calendar_url' => env('PAPRIKA_MENU_PLAN_ICS_URL'),
    ],

    'marvin' => [
        'token' => env('MARVIN_API_TOKEN'),
    ],

    'calendar' => [
        'subscriptions' => env('CALENDAR_SUBSCRIPTIONS'),
    ],

    'tomtom' => [
        'key' => env('TOMTOM_API_KEY'),
    ],

    'mobile' => [
        'pairing_code' => env('MOBILE_PAIRING_CODE'),
    ],

];
