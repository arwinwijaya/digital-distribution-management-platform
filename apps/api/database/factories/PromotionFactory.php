<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-2 months', '+1 week');
        $end   = $this->faker->dateTimeBetween($start, '+2 months');

        return [
            'name'           => $this->faker->words(3, true),
            'description'    => $this->faker->sentence(),
            'discount_type'  => $this->faker->randomElement(['percentage', 'fixed']),
            'discount_value' => $this->faker->randomFloat(2, 1, 50),
            'max_discount'   => null,
            'product_id'     => null,
            'min_order'      => 0,
            'start_date'     => $start,
            'end_date'       => $end,
            'is_active'      => true,
            'broadcast_at'   => null,
            'created_by'     => User::factory(),
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn () => [
            'discount_type'  => 'percentage',
            'discount_value' => $this->faker->randomFloat(2, 5, 50),
            'max_discount'   => $this->faker->randomFloat(2, 1000, 10000),
        ]);
    }

    public function fixed(): static
    {
        return $this->state(fn () => [
            'discount_type'  => 'fixed',
            'discount_value' => $this->faker->randomFloat(2, 1000, 5000),
        ]);
    }

    public function active(): static
    {
        $start = now()->subMonth()->startOfDay();
        $end   = now()->addMonth()->endOfDay();

        return $this->state(fn () => [
            'is_active'  => true,
            'start_date' => $start,
            'end_date'   => $end,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
        ]);
    }

    public function broadcast(): static
    {
        return $this->state(fn () => [
            'broadcast_at' => now()->subDay(),
        ]);
    }

    public function notYetBroadcast(): static
    {
        return $this->state(fn () => [
            'broadcast_at' => null,
        ]);
    }
}
