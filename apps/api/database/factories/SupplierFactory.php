<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supplier> */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->supplier(),
            'name' => fake()->company(),
            'subscription_status' => 'inactive',
            'subscription_plan' => 'basic',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'subscription_status' => 'active',
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn () => [
            'subscription_plan' => 'premium',
        ]);
    }
}
