<?php

namespace Database\Factories;

use App\Models\AbExperiment;
use App\Models\RevenueLiftSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueLiftSnapshot>
 */
class RevenueLiftSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'experiment_id' => AbExperiment::factory(),
            'uplift' => null,
            'status' => 'insufficient-data',
            'method_version' => 'v1',
            'control_sample_size' => 0,
            'treatment_sample_size' => 0,
            'control_revenue' => '0.00',
            'treatment_revenue' => '0.00',
            'metadata' => null,
        ];
    }
}
