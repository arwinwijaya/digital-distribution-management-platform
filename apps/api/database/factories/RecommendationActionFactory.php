<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\RecommendationAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationAction>
 */
class RecommendationActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_event_id' => null,
            'outlet_id' => Outlet::factory(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'executed_by' => null,
            'type' => fake()->randomElement(['draft_order', 'draft_campaign']),
            'status' => 'draft',
            'payload' => ['items' => [['product_id' => 1, 'quantity' => 2]]],
            'idempotency_key' => fake()->unique()->bothify('phase9-####-????'),
            'idempotency_payload_hash' => hash('sha256', (string) fake()->uuid()),
            'approved_at' => null,
            'executed_at' => null,
            'rejection_reason' => null,
            'execution_result' => null,
            'method' => 'deterministic',
            'method_version' => 'v1',
            'fallback' => false,
            'data_sufficiency' => 'sufficient',
            'metadata' => null,
        ];
    }
}
