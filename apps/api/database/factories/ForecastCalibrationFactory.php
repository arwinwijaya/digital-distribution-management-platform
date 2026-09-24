<?php

namespace Database\Factories;

use App\Models\ForecastCalibration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastCalibration>
 */
class ForecastCalibrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dimension_key' => fake()->unique()->bothify('phase9-dim-####-????'),
            'bias_factor' => '1.0000',
            'seasonality_factor' => '1.0000',
            'method_version' => 'v1',
            'fallback' => false,
            'sample_size' => fake()->numberBetween(30, 365),
            'metadata' => null,
        ];
    }
}
