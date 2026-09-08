<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(1000, 100000),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'stock_quantity' => fake()->numberBetween(0, 500),
            'category' => fake()->randomElement(['food', 'beverage', 'household', 'personal_care']),
            'is_active' => true,
        ];
    }
}
