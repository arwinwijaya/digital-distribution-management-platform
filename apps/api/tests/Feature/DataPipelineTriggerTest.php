<?php

namespace Tests\Feature;

use App\Models\DataMetricDefinition;
use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DataPipelineTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'pipeline-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    // ------------------------------------------------------------------ //
    // Cycle 1 — overlapping run is prevented                              //
    // ------------------------------------------------------------------ //

    public function test_overlapping_run_is_prevented(): void
    {
        // Arrange: create an active (running) pipeline run
        DataPipelineRun::create([
            'run_uuid' => '00000000-0000-4000-8000-0000000000a1',
            'status' => 'running',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-13',
            'window_end' => '2026-09-14',
            'timezone' => 'Asia/Jakarta',
        ]);

        $runCountBefore = DataPipelineRun::count();

        // Act: try to trigger via Artisan command
        $this->artisan('data:pipeline')
            ->expectsOutput('Pipeline run is skipped: another run is already active.');

        // Act: try to trigger via admin HTTP endpoint
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/admin/pipeline/manual-trigger');

        // Assert: second run is rejected
        $response->assertStatus(409)
            ->assertJsonPath('status', 'conflict');

        // Assert: no new run was created (only the original)
        $this->assertSame($runCountBefore, DataPipelineRun::count());
    }

    // ------------------------------------------------------------------ //
    // Cycle 2 — admin manually triggers a failed pipeline                //
    // ------------------------------------------------------------------ //

    public function test_admin_manually_triggers_a_failed_pipeline(): void
    {
        // Arrange: create a failed run with error recorded
        $failedRun = DataPipelineRun::create([
            'run_uuid' => '00000000-0000-4000-8000-0000000000f1',
            'status' => 'failed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-12',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
            'error_message' => 'stage [geographic] failed: connection timeout',
        ]);

        // Act: authenticated admin invokes manual trigger
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/admin/pipeline/manual-trigger');

        // Assert: accepted with a NEW run identity
        $response->assertStatus(202)
            ->assertJsonPath('status', 'accepted');

        $newRunId = $response->json('data.run_uuid');
        $this->assertNotNull($newRunId, 'New run UUID must be returned.');
        $this->assertNotSame(
            $failedRun->run_uuid,
            $newRunId,
            'Manual rerun must create a new run identity, not reuse the failed one.'
        );

        // Assert: new run exists in the database with a fresh UUID
        $this->assertDatabaseHas('data_pipeline_runs', [
            'run_uuid' => $newRunId,
            'status' => 'completed',
        ]);

        // Assert: no automatic retry occurred — the failed run remains failed
        $this->assertDatabaseHas('data_pipeline_runs', [
            'run_uuid' => $failedRun->run_uuid,
            'status' => 'failed',
        ]);
    }

    // ------------------------------------------------------------------ //
    // Cycle 3 — scheduled pipeline runs at 02:00 WIB                     //
    // ------------------------------------------------------------------ //

    public function test_scheduled_pipeline_runs_at_0200_wib(): void
    {
        // Arrange: freeze time to a known Jakarta moment
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Act: invoke the scheduled Artisan command directly
        $this->artisan('data:pipeline')
            ->assertExitCode(0);

        // Assert: a pipeline run was created with the prior-day/rolling window
        $run = DataPipelineRun::latest()->first();
        $this->assertNotNull($run, 'A pipeline run must be created.');
        $this->assertSame('completed', $run->status);
        $this->assertSame('v1', $run->pipeline_version);

        // Assert: window uses prior-day (Sep 13) and rolling 30-day (Aug 15-Sep 13)
        $this->assertSame('2026-08-15', $run->window_start->toDateString());
        $this->assertSame('2026-09-13', $run->window_end->toDateString());
        $this->assertSame('Asia/Jakarta', $run->timezone);

        // Assert: a published active snapshot was created
        $snapshot = DataSnapshot::where('is_active', true)->where('status', 'published')->first();
        $this->assertNotNull($snapshot, 'An active published snapshot must exist.');
        $this->assertSame($run->id, $snapshot->run_id);

        // Assert: scheduler registration uses Asia/Jakarta timezone
        // Booting schedule:list populates the shared Schedule singleton.
        $this->artisan('schedule:list');
        $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();
        $pipelineEvent = null;
        foreach ($events as $event) {
            if (str_contains($event->command ?? '', 'data:pipeline')) {
                $pipelineEvent = $event;
                break;
            }
        }
        $this->assertNotNull($pipelineEvent, 'The data:pipeline command must be registered in the scheduler.');
        $this->assertSame('Asia/Jakarta', $pipelineEvent->timezone);
        $this->assertSame('0 2 * * *', $pipelineEvent->expression);

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------ //
    // Cycle 4 — admin status returns current run, snapshot, window        //
    // ------------------------------------------------------------------ //

    public function test_admin_status_returns_current_run_active_publication_version_and_source_window(): void
    {
        // Arrange: create a completed run with a published active snapshot
        $run = DataPipelineRun::create([
            'run_uuid' => '00000000-0000-4000-8000-0000000000c1',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ]);

        $snapshot = DataSnapshot::create([
            'run_id' => $run->id,
            'snapshot_uuid' => '00000000-0000-4000-8000-0000000000c2',
            'version' => 3,
            'status' => 'staged',
            'is_active' => false,
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ]);

        // Publish the snapshot (values must be staged first)
        $metricDef = DataMetricDefinition::create([
            'key' => 'test_metric',
            'name' => 'Test Metric',
            'method_version' => 'v1',
        ]);
        DataSnapshotValue::create([
            'snapshot_id' => $snapshot->id,
            'metric_definition_id' => $metricDef->id,
            'section' => 'geographic',
            'dimension_key' => 'territory:1',
            'dimension' => ['territory_id' => 1],
            'value' => ['total_sales' => '100.00'],
        ]);

        \Illuminate\Support\Facades\DB::table('data_snapshots')
            ->where('id', $snapshot->id)
            ->update([
                'status' => 'published',
                'is_active' => true,
                'published_at' => now(),
            ]);

        // Act: authenticated admin requests pipeline status
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/pipeline/status');

        // Assert: run status and pipeline version
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.run.pipeline_version', 'v1');

        // Assert: active snapshot version
        $response->assertJsonPath('data.snapshot.version', 3);

        // Assert: window metadata with explicit Asia/Jakarta timezone
        $response->assertJsonPath('data.window.start', '2026-08-15')
            ->assertJsonPath('data.window.end', '2026-09-13')
            ->assertJsonPath('data.window.timezone', 'Asia/Jakarta');

        // Assert: unauthenticated request returns 401
        // (flush admin headers: withHeaders() persists across requests in one test)
        $this->flushHeaders();
        $this->getJson('/api/admin/pipeline/status')
            ->assertUnauthorized();

        // Assert: non-admin caller receives 403
        $outletUser = User::factory()->outlet()->create([
            'email' => 'pipeline-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email,
            'password' => 'password123',
        ])->json('data.token');

        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->getJson('/api/admin/pipeline/status')
            ->assertForbidden();
    }
}
