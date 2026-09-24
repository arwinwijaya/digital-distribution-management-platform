<?php

namespace Database\Factories;

use App\Models\AbExperiment;
use App\Models\AbExperimentAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbExperimentAssignment>
 */
class AbExperimentAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'experiment_id' => AbExperiment::factory(),
            'subject_key' => fake()->unique()->bothify('phase9-subject-####-????'),
            'bucket' => fake()->randomElement(['control', 'treatment']),
            'assigned_at' => now(),
        ];
    }
}
