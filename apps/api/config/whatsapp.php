<?php

return [
    // Keep the core API usable while Business approval or credentials are pending.
    'enabled' => (bool) env('WHATSAPP_ENABLED', true),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
    'verify_signature' => (bool) env('WHATSAPP_VERIFY_SIGNATURE', true),
    'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v20.0'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'catalog_limit' => (int) env('WHATSAPP_CATALOG_LIMIT', 20),
];
