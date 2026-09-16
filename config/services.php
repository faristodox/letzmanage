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

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'google_calendar' => [
        'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
        'redirect_uri_shared' => env('GOOGLE_CALENDAR_REDIRECT_URI_SHARED'),
        'redirect_uri_individual' => env('GOOGLE_CALENDAR_REDIRECT_URI_INDIVIDUAL'),
    ],

    'google_speech' => [
        'credentials_path' => env('GOOGLE_SPEECH_CREDENTIALS_PATH', storage_path('app/google/speech-service-account.json')),
        'bucket' => env('GCS_BUCKET'),
        'language_code' => env('GOOGLE_SPEECH_LANGUAGE_CODE', 'en-US'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // 'gemini-flash-latest' is a self-updating alias to Google's current
        // recommended flash model, rather than a pinned version — Google
        // deprecates specific version numbers quickly (2.0 already dead as
        // of mid-2026, 2.5 Pro dying October 2026), so pinning one here
        // would silently break in a few months.
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    ],

    'spi' => [
        'username' => env('SPI_USERNAME'),
        'password' => env('SPI_PASSWORD'),
        'ssl_verify' => env('SPI_SSL_VERIFY', true),
    ],

];
