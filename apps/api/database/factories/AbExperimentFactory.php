<?php

namespace Database\Factories;

use App\Models\AbExperiment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbExperiment>
 */
class AbExperimentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'experiment_key' => fake()->unique()->bothify('phase9-exp-####-????'),
            'name' => fake()->words(3, true),
            'status' => 'draft',
            'minimum_sample_size' => 30,
            'configuration' => null,
            'created_by' => User::factory(),
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}
