<?php

return [
    // Revenue hook: this rate is snapshotted on every order so historical
    // transactions are not changed when the configured rate changes.
    'commission_percentage' => (float) env('ORDER_COMMISSION_PERCENTAGE', 2.00),

    // Test-only request race barrier. Production never sets these values.
    'concurrency_barrier_dir' => env('ORDER_CONCURRENCY_BARRIER_DIR'),
    'concurrency_barrier_name' => env('ORDER_CONCURRENCY_BARRIER_NAME'),
    'concurrency_barrier_participant' => env('ORDER_CONCURRENCY_BARRIER_PARTICIPANT'),
];
