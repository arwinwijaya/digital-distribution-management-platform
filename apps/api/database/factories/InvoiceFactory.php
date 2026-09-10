<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = fake()->dateTimeBetween('-30 days', 'today');
        $dueDate = (clone $issueDate)->modify('+7 days');

        return [
            'order_id' => null,
            'outlet_id' => null,
            'invoice_number' => 'INV-'.strtoupper(fake()->unique()->bothify('########')),
            'issue_date' => $issueDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'balance_amount' => 100000,
            'status' => Invoice::UNPAID,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_amount' => $attributes['total_amount'],
            'balance_amount' => 0,
            'status' => Invoice::PAID,
        ]);
    }
}
