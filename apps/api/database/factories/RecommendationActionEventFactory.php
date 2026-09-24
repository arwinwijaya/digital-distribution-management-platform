<?php

namespace Database\Factories;

use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationActionEvent>
 */
class RecommendationActionEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'recommendation_action_id' => RecommendationAction::factory(),
            'event_type' => fake()->randomElement(['created', 'approved', 'rejected', 'executed', 'failed', 'cancelled']),
            'actor_id' => User::factory(),
            'metadata' => ['at' => now()->toISOString()],
            'occurred_at' => now(),
        ];
    }
}
