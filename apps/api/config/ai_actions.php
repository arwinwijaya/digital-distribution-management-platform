<?php

return [
    // The action/model seam is opt-in but deterministic by default.
    'enabled' => (bool) env('AI_ACTIONS_ENABLED', true),
    'kill_switch' => (bool) env('AI_ACTIONS_KILL_SWITCH', false),
    'ml_adapter' => [
        'driver' => env('AI_ACTIONS_ML_ADAPTER_DRIVER', 'deterministic'),
        'timeout_ms' => (int) env('AI_ACTIONS_ML_ADAPTER_TIMEOUT_MS', 1000),
        'failure_threshold' => (int) env('AI_ACTIONS_ML_ADAPTER_FAILURE_THRESHOLD', 3),
        'cooldown_seconds' => (int) env('AI_ACTIONS_ML_ADAPTER_COOLDOWN_SECONDS', 60),
        // Shared cache key used by all resolver instances for circuit state.
        'circuit_key' => env('AI_ACTIONS_ML_ADAPTER_CIRCUIT_KEY', 'ai_actions:ml_adapter:circuit'),
    ],
];
