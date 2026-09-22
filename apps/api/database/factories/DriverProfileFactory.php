<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverProfile>
 */
class DriverProfileFactory extends Factory
{
    protected $model = DriverProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefixes = ['B', 'D', 'F', 'L', 'AB'];
        $suffixes = ['XYZ', 'ABC', 'KLM', 'PQR'];

        return [
            'user_id' => User::factory()->state(['role' => 'driver']),
            'vehicle_type' => fake()->randomElement(['motor', 'mobil', 'pickup', 'van']),
            'plate_number' => sprintf(
                '%s %d %s',
                fake()->randomElement($prefixes),
                fake()->numberBetween(1000, 9999),
                fake()->randomElement($suffixes),
            ),
            'capacity_kg' => fake()->numberBetween(20, 500),
            'service_territory_id' => null,
            'shift_start' => '08:00:00',
            'shift_end' => '17:00:00',
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
