<?php

/**
 * CORS policy.
 *
 * Previously absent, which made Laravel's HandleCors fall back to the framework
 * default (`allowed_origins => ['*']`) — any website could call the API from a
 * browser. Origins are now an explicit allow-list from CORS_ALLOWED_ORIGINS
 * (comma-separated). Because the API authenticates with a Bearer JWT (not
 * cookies), `supports_credentials` stays false.
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
