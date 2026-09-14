<?php

namespace Tests\Feature;

use App\Models\DataMetricDefinition;
use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;
use App\Services\DataPipelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataPipelineServiceTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ //
    // Cycle 1 — failed stage preserves the last successful snapshot        //
    // ------------------------------------------------------------------ //

    public function test_failed_stage_preserves_last_successful_snapshot(): void
    {
        // -- Arrange: create a previous successful published snapshot --
        $previousRun = DataPipelineRun::create([
            'run_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-13',
        ]);

        $previousSnapshot = DataSnapshot::create([
            'run_id' => $previousRun->id,
            'snapshot_uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'version' => 1,
            'status' => 'staged',
            'is_active' => false,
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-13',
        ]);

        $metricDef = DataMetricDefinition::create([
            'key' => 'geographic_sales',
            'name' => 'Geographic Sales',
            'method_version' => 'v1',
        ]);

        DataSnapshotValue::create([
            'snapshot_id' => $previousSnapshot->id,
            'metric_definition_id' => $metricDef->id,
            'section' => 'geographic',
            'dimension_key' => 'territory:1',
            'dimension' => ['territory_id' => 1],
            'value' => ['total_sales' => '500.00', 'total_orders' => 10],
        ]);

        // Publish prior snapshot: values must be staged before the
        // published-status transition (published rows are immutable).
        DB::table('data_snapshots')->where('id', $previousSnapshot->id)->update([
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
        ]);
        $previousSnapshot->refresh();

        // -- Act: run pipeline with a stage that fails --
        $service = new DataPipelineService();
        $service->registerStage('geographic', function (array $window): array {
            return ['territory:1' => ['total_sales' => '600.00', 'total_orders' => 12]];
        });
        $service->registerStage('supplier', function (array $window): array {
            throw new \RuntimeException('supplier stage intentionally failed');
        });

        // Freeze clock to deterministic Jakarta window
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $service->run();

        // -- Assert: run is marked failed, previous snapshot is still active --
        $failedRun = DataPipelineRun::where('status', 'failed')->latest()->first();
        $this->assertNotNull($failedRun, 'A failed pipeline run should exist.');
        $this->assertNotNull($failedRun->error_message, 'Failed run should have an error message.');
        $this->assertStringContainsString('supplier', strtolower($failedRun->error_message));

        // Previous snapshot remains the only active snapshot
        $activeSnapshot = DataSnapshot::where('is_active', true)->first();
        $this->assertNotNull($activeSnapshot, 'The previous active snapshot should still exist.');
        $this->assertSame($previousSnapshot->id, $activeSnapshot->id, 'The active snapshot should be the previous one.');
        $this->assertSame('published', $activeSnapshot->status);

        // No partial snapshot values leaked
        $newSnapshot = DataSnapshot::where('run_id', $failedRun->id)->first();
        $this->assertNull($newSnapshot, 'No snapshot should have been created for the failed run.');
    }

    // ------------------------------------------------------------------ //
    // Cycle 2 — empty data is a valid safe run                           //
    // ------------------------------------------------------------------ //

    public function test_empty_data_is_a_valid_safe_run(): void
    {
        // -- Arrange: create a previous completed snapshot with some history  --
        $previousRun = DataPipelineRun::create([
            'run_uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
        ]);

        $previousSnapshot = DataSnapshot::create([
            'run_id' => $previousRun->id,
            'snapshot_uuid' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            'version' => 1,
            'status' => 'staged',
            'is_active' => false,
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
        ]);

        $existingMetric = DataMetricDefinition::create([
            'key' => 'old_metric',
            'name' => 'Old Metric',
            'method_version' => 'v1',
        ]);

        DataSnapshotValue::create([
            'snapshot_id' => $previousSnapshot->id,
            'metric_definition_id' => $existingMetric->id,
            'section' => 'geographic',
            'dimension_key' => 'territory:99',
            'dimension' => ['territory_id' => 99],
            'value' => ['total_sales' => '120.00', 'total_orders' => 3],
        ]);

        // Publish prior snapshot (staged before status flip).
        DB::table('data_snapshots')->where('id', $previousSnapshot->id)->update([
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
        ]);
        $previousSnapshot->refresh();

        $priorSnapshotCount = DataSnapshot::count();
        $priorSnapshotValueCount = DataSnapshotValue::count();
        $priorRunCount = DataPipelineRun::count();

        // -- Act: run pipeline with NO stages registered (empty data source)  --
        $service = new DataPipelineService();

        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $service->run();

        // -- Assert: a new completed empty snapshot was published            --
        $newRun = DataPipelineRun::where('status', 'completed')->latest()->first();
        $this->assertNotNull($newRun, 'A completed empty-data run must exist.');
        $this->assertSame('v1', $newRun->pipeline_version);

        $activeSnapshot = DataSnapshot::where('is_active', true)->latest()->first();
        $this->assertNotNull($activeSnapshot, 'A new active snapshot should be published even with empty data.');
        $this->assertSame('published', $activeSnapshot->status);
        $this->assertGreaterThan($previousSnapshot->version, $activeSnapshot->version,
            'Empty-data snapshot must have a higher version than the previous.');

        // Each section produced a value row (even if payload is empty []).
        foreach (DataPipelineService::SECTIONS as $section) {
            $hasSection = DataSnapshotValue::where('snapshot_id', $activeSnapshot->id)
                ->where('section', $section)
                ->exists();
            $this->assertTrue($hasSection, "Empty-data snapshot must include section [$section].");
        }

        // Audit history is NOT deleted.
        $this->assertGreaterThan($priorSnapshotCount, DataSnapshot::count(),
            'Audit history must not be deleted by an empty-data run.');
        $this->assertGreaterThan($priorSnapshotValueCount, DataSnapshotValue::count(),
            'Audit history values must not be deleted by an empty-data run.');
        $this->assertGreaterThan($priorRunCount, DataPipelineRun::count(),
            'Audit run history must not be deleted by an empty-data run.');

        // Previous snapshot still exists in the audit trail.
        $this->assertDatabaseHas('data_snapshots', [
            'id' => $previousSnapshot->id,
            'status' => 'published',
        ]);
    }
}
