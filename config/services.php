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

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
        'jwt_audience' => env('SUPABASE_JWT_AUDIENCE', 'authenticated'),
        'jwt_clock_skew' => env('SUPABASE_JWT_CLOCK_SKEW', 30),
        'jwt_issuer' => env('SUPABASE_JWT_ISSUER', rtrim((string) env('SUPABASE_URL'), '/').'/auth/v1'),
        'jwks_url' => env('SUPABASE_JWKS_URL', env('SUPABASE_URL') ? rtrim((string) env('SUPABASE_URL'), '/').'/auth/v1/.well-known/jwks.json' : null),
        'jwks_cache_ttl' => env('SUPABASE_JWKS_CACHE_TTL', 3600),
        'jwks_timeout' => env('SUPABASE_JWKS_TIMEOUT', 5),
        'last_login_debounce' => env('SUPABASE_LAST_LOGIN_DEBOUNCE', 300),
        'profile_sync_http' => filter_var(env('SUPABASE_PROFILE_SYNC_HTTP', false), FILTER_VALIDATE_BOOLEAN),
        'profile_sync_url' => env('SUPABASE_PROFILE_SYNC_URL', env('SUPABASE_URL') ? rtrim((string) env('SUPABASE_URL'), '/').'/auth/v1/user' : null),
        'profile_sync_timeout' => env('SUPABASE_PROFILE_SYNC_TIMEOUT', 5),
        'profile_sync_cache_ttl' => env('SUPABASE_PROFILE_SYNC_CACHE_TTL', 300),
    ],

    'github' => [
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'app_id' => env('GITHUB_APP_ID'),
        'app_private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'timeout' => (int) env('GITHUB_TIMEOUT', 15),
        'token_cache_ttl' => (int) env('GITHUB_TOKEN_CACHE_TTL', 3300),
        'retries' => (int) env('GITHUB_RETRIES', 2),
    ],

];
