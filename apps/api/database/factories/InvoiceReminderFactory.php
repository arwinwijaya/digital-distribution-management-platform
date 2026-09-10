<?php

namespace Database\Factories;

use App\Models\InvoiceReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceReminder>
 */
class InvoiceReminderFactory extends Factory
{
    protected $model = InvoiceReminder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventDate = fake()->dateTimeBetween('-7 days', 'today');
        $eventDateValue = $eventDate->format('Y-m-d');
        $eventType = InvoiceReminder::EVENT_H_MINUS_ONE;

        return [
            'invoice_id' => null,
            'event_type' => $eventType,
            'event_date' => $eventDateValue,
            'status' => InvoiceReminder::PENDING,
            'attempts' => 0,
            'next_attempt_at' => null,
            'sent_at' => null,
            'failed_at' => null,
            'last_error' => null,
            'idempotency_key' => 'invoice-reminder-'.strtolower(fake()->unique()->bothify('################')),
            'provider_message_id' => null,
            'metadata' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceReminder::SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceReminder::FAILED,
            'failed_at' => now(),
        ]);
    }
}
