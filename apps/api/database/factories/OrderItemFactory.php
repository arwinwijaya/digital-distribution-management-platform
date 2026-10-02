<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(1000, 50000);
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_id' => null,
            'product_id' => null,
            'product_name_snapshot' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
        ];
    }

    /**
     * Configure for a specific product with frozen snapshot.
     */
    public function forProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(fn () => [
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'subtotal' => (float) $product->price * $quantity,
        ]);
    }
}
