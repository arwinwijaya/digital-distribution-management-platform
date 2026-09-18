<?php

return [
    // Revenue hook: this rate is snapshotted on every order so historical
    // transactions are not changed when the configured rate changes.
    'commission_percentage' => (float) env('ORDER_COMMISSION_PERCENTAGE', 2.00),

    // Test-only request race barrier. Gated by an explicit boolean so production
    // code paths skip the barrier call entirely (not just the no-op inside the class).
    'concurrency_barrier_enabled' => (bool) env('ORDER_CONCURRENCY_BARRIER_ENABLED', false),
    'concurrency_barrier_dir' => env('ORDER_CONCURRENCY_BARRIER_DIR'),
    'concurrency_barrier_name' => env('ORDER_CONCURRENCY_BARRIER_NAME'),
    'concurrency_barrier_participant' => env('ORDER_CONCURRENCY_BARRIER_PARTICIPANT'),
    'concurrency_barrier_sections' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ORDER_CONCURRENCY_BARRIER_SECTIONS', '')),
    ))),
];
