<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Instance URL
    |--------------------------------------------------------------------------
    |
    | The URL of your Snapsbrew instance, for example
    | "https://snapsbrew.example.com". Each user runs an own instance, so
    | this value is required. The SDK removes a trailing "/api".
    |
    */

    'url' => env('SNAPSBREW_URL'),

    /*
    |--------------------------------------------------------------------------
    | API token
    |--------------------------------------------------------------------------
    |
    | A personal access token with the "api_requests" ability. The SDK sends
    | it as a bearer token.
    |
    */

    'token' => env('SNAPSBREW_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Signing key
    |--------------------------------------------------------------------------
    |
    | The key for signed image URLs. This key is a secret. The SDK uses it
    | only on the server.
    |
    */

    'signing_key' => env('SNAPSBREW_SIGNING_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Timeout and retries
    |--------------------------------------------------------------------------
    |
    | The request timeout in seconds, and the number of retries after a 429,
    | a 5xx or a connection error.
    |
    */

    'timeout' => (float) env('SNAPSBREW_TIMEOUT', 60),

    'retries' => (int) env('SNAPSBREW_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The webhook signing secret of your company, with its "whsec_" prefix.
    | Account > Developers shows it. The tolerance is the maximum difference
    | in seconds between the signing time and the current time.
    |
    */

    'webhook' => [
        'secret' => env('SNAPSBREW_WEBHOOK_SECRET'),
        'tolerance' => (int) env('SNAPSBREW_WEBHOOK_TOLERANCE', 300),
    ],

];
