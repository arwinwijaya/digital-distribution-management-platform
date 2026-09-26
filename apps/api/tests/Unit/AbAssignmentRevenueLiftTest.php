<?php

namespace Tests\Unit;

use App\Models\AbExperiment;
use App\Models\AbExperimentAssignment;
use App\Models\RevenueLiftSnapshot;
use App\Services\AbExperimentService;
use App\Services\RevenueLiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbAssignmentRevenueLiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_is_deterministic_and_persists_unique_assignment(): void
    {
        $experiment = AbExperiment::factory()->create(['experiment_key' => 'exp-checkout-banner']);
        $service = new AbExperimentService();

        $first = $service->assign($experiment->experiment_key, 'outlet-101');
        $second = $service->assign($experiment->experiment_key, 'outlet-101');

        $this->assertSame($first->bucket, $second->bucket);
        $this->assertSame($experiment->id, $first->experiment_id);
        $this->assertSame('outlet-101', $first->subject_key);
        $this->assertContains($first->bucket, AbExperimentAssignment::BUCKETS);
        $this->assertSame(1, AbExperimentAssignment::count());
    }

    public function test_assign_is_balanced_and_reproducible_across_subjects(): void
    {
        $experiment = AbExperiment::factory()->create(['experiment_key' => 'exp-balanced']);
        $service = new AbExperimentService();

        $buckets = [];
        for ($i = 0; $i < 60; $i++) {
            $buckets[] = $service->assign($experiment->experiment_key, 'subject-'.$i)->bucket;
        }

        $this->assertContains('control', $buckets);
        $this->assertContains('treatment', $buckets);

        // Re-running the same subjects yields identical buckets.
        for ($i = 0; $i < 60; $i++) {
            $this->assertSame($buckets[$i], $service->assign($experiment->experiment_key, 'subject-'.$i)->bucket);
        }
        $this->assertSame(60, AbExperimentAssignment::count());
    }

    public function test_computeLift_sufficient_data_persists_snapshot(): void
    {
        $experiment = AbExperiment::factory()->create(['minimum_sample_size' => 3]);
        $service = new RevenueLiftService();

        $snapshot = $service->computeLift($experiment, [
            'control' => [
                ['revenue' => '100.00'],
                ['revenue' => '100.00'],
                ['revenue' => '100.00'],
            ],
            'treatment' => [
                ['revenue' => '130.00'],
                ['revenue' => '130.00'],
                ['revenue' => '130.00'],
            ],
        ]);

        $this->assertSame('computed', $snapshot->status);
        $this->assertSame(3, $snapshot->control_sample_size);
        $this->assertSame(3, $snapshot->treatment_sample_size);
        $this->assertEqualsWithDelta(0.30, (float) $snapshot->uplift, 0.000001);
        $this->assertSame('300.00', (string) $snapshot->control_revenue);
        $this->assertSame('390.00', (string) $snapshot->treatment_revenue);
        $this->assertSame(RevenueLiftService::METHOD_VERSION, $snapshot->method_version);
        $this->assertSame(1, RevenueLiftSnapshot::count());
    }

    public function test_computeLift_insufficient_data_is_explicit_not_zero(): void
    {
        $experiment = AbExperiment::factory()->create(['minimum_sample_size' => 30]);
        $service = new RevenueLiftService();

        $snapshot = $service->computeLift($experiment, [
            'control' => [['revenue' => '100.00']],
            'treatment' => [['revenue' => '130.00']],
        ]);

        $this->assertSame('insufficient-data', $snapshot->status);
        $this->assertNull($snapshot->uplift);
        $this->assertSame(1, $snapshot->control_sample_size);
        $this->assertSame(1, $snapshot->treatment_sample_size);
    }

    public function test_computeLift_empty_data_is_safe(): void
    {
        $experiment = AbExperiment::factory()->create(['minimum_sample_size' => 30]);
        $snapshot = (new RevenueLiftService())->computeLift($experiment, ['control' => [], 'treatment' => []]);

        $this->assertSame('insufficient-data', $snapshot->status);
        $this->assertNull($snapshot->uplift);
        $this->assertSame(0, $snapshot->control_sample_size);
        $this->assertSame(0, $snapshot->treatment_sample_size);
    }

    public function test_computeLift_is_deterministic_for_identical_inputs(): void
    {
        $experiment = AbExperiment::factory()->create(['minimum_sample_size' => 2]);
        $service = new RevenueLiftService();
        $samples = [
            'control' => [['revenue' => '50.00'], ['revenue' => '150.00']],
            'treatment' => [['revenue' => '100.00'], ['revenue' => '200.00']],
        ];

        $a = $service->computeLift($experiment, $samples);
        $b = $service->computeLift($experiment, $samples);

        $this->assertSame((string) $a->uplift, (string) $b->uplift);
        $this->assertSame($a->control_revenue, $b->control_revenue);
        $this->assertSame($a->treatment_revenue, $b->treatment_revenue);
    }

    public function test_computeLift_zero_control_revenue_is_insufficient_not_perfect(): void
    {
        $experiment = AbExperiment::factory()->create(['minimum_sample_size' => 2]);
        $snapshot = (new RevenueLiftService())->computeLift($experiment, [
            'control' => [['revenue' => '0.00'], ['revenue' => '0.00']],
            'treatment' => [['revenue' => '10.00'], ['revenue' => '20.00']],
        ]);

        $this->assertSame('insufficient-data', $snapshot->status);
        $this->assertNull($snapshot->uplift);
    }
}
