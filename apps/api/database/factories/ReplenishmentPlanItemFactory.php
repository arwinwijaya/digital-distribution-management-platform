<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReplenishmentPlanItem>
 */
class ReplenishmentPlanItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'replenishment_plan_id' => ReplenishmentPlan::factory(),
            'product_id' => Product::factory(),
            'reorder_quantity' => fake()->randomFloat(3, 1, 100),
            'data_sufficiency' => 'sufficient',
            'metadata' => null,
        ];
    }
}
