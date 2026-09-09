<?php

return [
    // Keep the core API usable while Business approval or credentials are pending.
    'enabled' => (bool) env('WHATSAPP_ENABLED', true),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v20.0'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'catalog_limit' => (int) env('WHATSAPP_CATALOG_LIMIT', 20),
    // A sending row is reclaimable only after this lease expires. Retries use
    // the stable provider idempotency key to make an unknown provider outcome safe.
    'send_lease_seconds' => (int) env('WHATSAPP_SEND_LEASE_SECONDS', 300),
    // Opt-in only: the PostgreSQL worker race suite enables this test barrier.
    'concurrency_barrier_enabled' => (bool) env('WHATSAPP_CONCURRENCY_BARRIER_ENABLED', false),
];
