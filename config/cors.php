<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| The Sentrix dashboard is a separate-origin SPA using Sanctum bearer tokens.
| Allow it to call the versioned API and the broadcasting auth endpoint.
| Bearer tokens (not cookies) carry auth, so credentials are not required.
|
| Set DASHBOARD_URL in .env for non-local origins (e.g. https://app.sentrix.ng).
|
*/

return [

    'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('DASHBOARD_URL', 'http://localhost:5173'),
    ])),

    // In local development, accept any localhost/127.0.0.1 port so `npm run dev`
    // (5173, or 5174+ when busy) and `npm run preview` (4173) all work without
    // editing env. Production keeps the explicit allow-list above only.
    'allowed_origins_patterns' => env('APP_ENV') === 'local'
        ? ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#']
        : [],

    // Includes Authorization and X-Organization (the dashboard sends both).
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer tokens, not cookies — no cross-site credentials needed.
    'supports_credentials' => false,
];
