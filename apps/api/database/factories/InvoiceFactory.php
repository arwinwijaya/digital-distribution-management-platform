<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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

    /**
     * Partially paid with a fixed paid amount.
     */
    public function partiallyPaid(float $paid = 30000): static
    {
        return $this->state(fn (array $attributes): array => [
            'paid_amount' => $paid,
            'balance_amount' => $attributes['total_amount'] - $paid,
            'status' => Invoice::PARTIALLY_PAID,
        ]);
    }

    /**
     * Overdue with due date in the past and outstanding balance.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'issue_date' => Carbon::today()->subDays(14)->toDateString(),
            'due_date' => Carbon::today()->subDays(7)->toDateString(),
            'balance_amount' => $attributes['total_amount'] - ($attributes['paid_amount'] ?? 0),
            'status' => Invoice::PARTIALLY_PAID,
        ]);
    }

    /**
     * Canonical dummy invoice matching DummySeeder::INVOICE_NUMBER.
     */
    public function dummy(): static
    {
        return $this->state(fn () => [
            'invoice_number' => \Database\Seeders\DummySeeder::INVOICE_NUMBER,
        ])->overdue()->partiallyPaid(30000);
    }
}