<?php

namespace Database\Factories;

use App\Models\ReplenishmentPlan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReplenishmentPlan>
 */
class ReplenishmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'status' => 'draft',
            'window_start' => now()->startOfMonth()->toDateString(),
            'window_end' => now()->endOfMonth()->toDateString(),
            'approved_at' => null,
            'executed_at' => null,
            'execution_result' => null,
            'metadata' => null,
        ];
    }
}
