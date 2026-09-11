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
    // Invoice reminder schedule settings.
    'reminder_time_zone' => 'Asia/Jakarta',
    'reminder_schedule' => [
        'h_minus_one_offset_days' => (int) env('WHATSAPP_REMINDER_H_MINUS_ONE_OFFSET_DAYS', 1),
        'overdue_offset_days' => (int) env('WHATSAPP_REMINDER_OVERDUE_OFFSET_DAYS', 0),
        'max_attempts' => (int) env('WHATSAPP_REMINDER_MAX_ATTEMPTS', 4),
        'backoff_minutes' => array_map('intval', array_filter(explode(',', (string) env('WHATSAPP_REMINDER_BACKOFF_MINUTES', '1,5,15')), fn ($v) => $v !== '')),
    ],
    'reminder_max_attempts' => (int) env('WHATSAPP_REMINDER_MAX_ATTEMPTS', 4),
    'reminder_backoff_minutes' => array_map('intval', array_filter(explode(',', (string) env('WHATSAPP_REMINDER_BACKOFF_MINUTES', '1,5,15')), fn ($v) => $v !== '')),
];
