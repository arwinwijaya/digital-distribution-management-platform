<?php

namespace Tests\Feature;

use App\Console\Commands\RunDataPipeline;
use App\Models\DataMetricDefinition;
use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\RecommendationEvent;
use App\Models\Supplier;
use App\Models\Territory;
use App\Models\User;
use App\Services\DataPipelineService;
use App\Services\GeographicAnalyticsService;
use App\Services\MeasurementService;
use App\Services\StockPlanningService;
use App\Services\SupplierPerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cross-unit integration tests proving atomic pipeline publication.
 *
 * These tests exercise the public Artisan scheduler, admin HTTP endpoints,
 * Eloquent services, database transactions and locks.  No production file
 * may be modified; test doubles are limited to the clock and external network.
 */
class DataIntelligenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'integration-admin@ddp.test',
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
    // Helpers                                                             //
    // ------------------------------------------------------------------ //

    /**
     * Bind a fully-staged DataPipelineService into the container.
     *
     * This allows the Artisan command and controller to resolve the same
     * instance with all four section callbacks registered.
     */
    protected function bindPipelineService(): void
    {
        $this->app->singleton(DataPipelineService::class, function (): DataPipelineService {
            $service = new DataPipelineService();
            $service->registerStage('geographic', (new GeographicAnalyticsService())->stageCallback());
            $service->registerStage('supplier', (new SupplierPerformanceService())->stageCallback());
            $service->registerStage('stock', (new StockPlanningService())->stageCallback());
            $service->registerStage('measurement', (new MeasurementService())->stageCallback());
            return $service;
        });
    }

    /**
     * Seed minimal fixtures for all four pipeline sections.
     *
     * Returns an array of created models for assertions where needed.
     */
    protected function seedAllSectionFixtures(): array
    {
        $territory = Territory::create(['name' => 'Jakarta Pusat', 'code' => 'JKP']);
        $outlet = Outlet::factory()->create([
            'territory_id' => $territory->id,
            'latitude' => -6.17,
            'longitude' => 106.84,
            'name' => 'Integration Outlet',
        ]);
        $supplier = Supplier::factory()->create(['lead_time_days' => 5]);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        // Geographic: eligible order within the 30-day window
        $order = Order::create([
            'order_id' => 'ORD-INT-001',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 250.00,
            'paid_amount' => 250.00,
            'commission_percentage' => 2,
            'idempotency_key' => 'int-geo-001',
            'due_date' => '2026-09-12',
        ]);
        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse('2026-09-10 10:00:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-09-10 10:00:00', 'Asia/Jakarta'),
        ]);
        $order->refresh();

        // Supplier: order item + completed delivery
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 50,
            'subtotal' => 250,
        ]);
        $driver = User::factory()->driver()->create([
            'email' => 'integration-driver@ddp.test',
        ]);
        Delivery::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by_id' => $this->admin->id,
            'status' => Delivery::DELIVERED,
            'assigned_at' => Carbon::parse('2026-09-10 08:00:00', 'Asia/Jakarta'),
            'started_at' => Carbon::parse('2026-09-10 09:00:00', 'Asia/Jakarta'),
            'delivered_at' => Carbon::parse('2026-09-10 11:00:00', 'Asia/Jakarta'),
        ]);

        // Measurement: recommendation funnel events
        RecommendationEvent::create([
            'event_uuid' => 'int-evt-displayed',
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => Carbon::parse('2026-09-10 14:00:00', 'Asia/Jakarta'),
        ]);
        RecommendationEvent::create([
            'event_uuid' => 'int-evt-clicked',
            'event_type' => 'clicked',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => Carbon::parse('2026-09-10 14:05:00', 'Asia/Jakarta'),
        ]);

        return compact('territory', 'outlet', 'supplier', 'product', 'order', 'orderItem', 'driver');
    }

    /**
     * Refresh the admin token when prior HTTP calls raced with the frozen clock.
     *
     * The suite freezes time at 02:00, which can invalidate JWT tokens Issued
     * After Expiry in real time. Login again to refresh the token so follow-up
     * HTTP assertions do not false-negative.
     */
    protected function refreshToken(): void
    {
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    /**
     * Assert that every BI consumer returns the expected active snapshot version.
     */
    protected function assertAllConsumersReportVersion(int $expectedVersion, array $expectedWindow): void
    {
        // If the frozen clock invalidated the JWT expiry claim, re-authenticate.
        $probe = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/pipeline/status');
        if ($probe->status() === 401) {
            $this->refreshToken();
        }

        // 1. Pipeline status endpoint
        $statusResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/pipeline/status');
        $statusResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.snapshot.version', $expectedVersion)
            ->assertJsonPath('data.window.start', $expectedWindow['start'])
            ->assertJsonPath('data.window.end', $expectedWindow['end'])
            ->assertJsonPath('data.window.timezone', $expectedWindow['timezone']);

        // 2. Geographic BI consumer
        $geoResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');
        $geoResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.snapshot_version', $expectedVersion)
            ->assertJsonPath('data.window.timezone', $expectedWindow['timezone']);

        // 3. Supplier performance BI consumer
        $supplierResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/suppliers');
        $supplierResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.snapshot_version', $expectedVersion)
            ->assertJsonPath('data.window.timezone', $expectedWindow['timezone']);

        // 4. Stock planning BI consumer
        $stockResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/stock-planning');
        $stockResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.snapshot_version', $expectedVersion)
            ->assertJsonPath('data.window.timezone', $expectedWindow['timezone']);

        // 5. Measurement BI consumer (recommendations endpoint)
        $measResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/measurement/recommendations');
        $measResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.snapshot_version', $expectedVersion)
            ->assertJsonPath('data.window.timezone', $expectedWindow['timezone']);
    }

    // ------------------------------------------------------------------ //
    // Cycle 1 — scheduled pipeline publishes a complete snapshot           //
    // ------------------------------------------------------------------ //

    public function test_scheduled_pipeline_publishes_a_complete_snapshot(): void
    {
        // Arrange: freeze Asia/Jakarta clock to a deterministic moment
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: seed source data for all four pipeline sections
        $this->seedAllSectionFixtures();

        // Arrange: bind a fully-staged DataPipelineService into the container
        // so the Artisan command and admin endpoints resolve the same instance.
        $this->bindPipelineService();

        // Expected 30-day rolling window: Aug 15 – Sep 13
        $expectedWindow = [
            'start' => '2026-08-15',
            'end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ];

        // Act: invoke the public scheduler command (Artisan boundary)
        $this->artisan('data:pipeline')
            ->assertExitCode(0);

        // Assert: exactly one completed run exists
        $completedRuns = DataPipelineRun::where('status', 'completed')->get();
        $this->assertSame(1, $completedRuns->count(), 'Exactly one completed run must exist after the scheduler.');
        $run = $completedRuns->first();
        $this->assertSame('v1', $run->pipeline_version);
        $this->assertSame('Asia/Jakarta', $run->timezone);

        // Assert: exactly one published active snapshot exists
        $activeSnapshot = DataSnapshot::where('is_active', true)
            ->where('status', 'published')
            ->first();
        $this->assertNotNull($activeSnapshot, 'An active published snapshot must exist.');
        $this->assertSame($run->id, $activeSnapshot->run_id);
        $this->assertSame('Asia/Jakarta', $activeSnapshot->timezone);

        // Assert: window metadata on the snapshot matches the expected rolling window
        $this->assertSame($expectedWindow['start'], $activeSnapshot->window_start->toDateString());
        $this->assertSame($expectedWindow['end'], $activeSnapshot->window_end->toDateString());

        // Assert: all four sections are present in the snapshot values
        $sections = DataSnapshotValue::where('snapshot_id', $activeSnapshot->id)
            ->pluck('section')
            ->unique()
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing(
            DataPipelineService::SECTIONS,
            $sections,
            'All four sections must be present in the published snapshot.'
        );

        // Assert: metric definitions exist for each section
        foreach (DataPipelineService::SECTIONS as $section) {
            $definition = DataMetricDefinition::where('key', "section_{$section}")->first();
            $this->assertNotNull($definition, "Metric definition for section [{$section}] must exist.");
        }

        // Assert: snapshot_version and lineage are recorded in the run
        $this->assertNotNull($run->fresh()->lineage, 'Run lineage must be recorded.');
        $this->assertArrayHasKey('snapshot_version', $run->fresh()->lineage);

        // Assert: all BI consumers report the same active snapshot version
        $this->assertAllConsumersReportVersion($activeSnapshot->version, $expectedWindow);

        // Assert: no partial/staged snapshots are visible
        $stagedSnapshots = DataSnapshot::where('status', 'staged')
            ->where('is_active', false)
            ->count();
        $this->assertSame(0, $stagedSnapshots, 'No staged snapshots should be visible.');

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------ //
    // Cycle 2 — failed stage preserves prior snapshot without partial     //
    // ------------------------------------------------------------------ //

    public function test_failed_stage_preserves_prior_snapshot_without_partial_output(): void
    {
        // Arrange: freeze Asia/Jakarta clock
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: seed source data for the first successful pipeline run
        $fixtures = $this->seedAllSectionFixtures();

        // Act 1: run the pipeline successfully to create a prior active snapshot
        $this->bindPipelineService();
        $this->artisan('data:pipeline')
            ->assertExitCode(0);

        $priorRun = DataPipelineRun::where('status', 'completed')->first();
        $priorSnapshot = DataSnapshot::where('is_active', true)->where('status', 'published')->first();
        $this->assertNotNull($priorSnapshot, 'A prior active snapshot must exist after the first run.');
        $priorVersion = $priorSnapshot->version;
        $priorSnapshotId = $priorSnapshot->id;
        $priorRunUuid = $priorRun->run_uuid;

        // Capture the full snapshot values for byte-for-byte stability check
        $priorValues = DataSnapshotValue::where('snapshot_id', $priorSnapshotId)
            ->orderBy('section')
            ->orderBy('dimension_key')
            ->get()
            ->map(fn (DataSnapshotValue $v) => [
                'section' => $v->section,
                'dimension_key' => $v->dimension_key,
                'value' => $v->value,
            ])
            ->all();

        // Act 2: bind a service where the supplier stage throws
        $this->app->singleton(DataPipelineService::class, function (): DataPipelineService {
            $service = new DataPipelineService();
            $service->registerStage('geographic', (new GeographicAnalyticsService())->stageCallback());
            $service->registerStage('supplier', function (): array {
                throw new \RuntimeException('supplier stage intentionally failed');
            });
            return $service;
        });

        $this->artisan('data:pipeline')
            ->assertExitCode(0);

        // Assert: a failed run is recorded with error lineage
        $failedRun = DataPipelineRun::where('status', 'failed')->latest()->first();
        $this->assertNotNull($failedRun, 'A failed pipeline run must exist.');
        $this->assertNotNull($failedRun->error_message, 'Failed run must have an error message.');
        $this->assertStringContainsString('supplier', strtolower($failedRun->error_message));

        // Assert: the prior snapshot remains the active published snapshot
        $currentActive = DataSnapshot::where('is_active', true)->where('status', 'published')->first();
        $this->assertNotNull($currentActive, 'An active published snapshot must still exist.');
        $this->assertSame($priorSnapshotId, $currentActive->id, 'The active snapshot must be the prior one.');
        $this->assertSame($priorVersion, $currentActive->version, 'The active snapshot version must not have changed.');

        // Assert: no snapshot was created for the failed run
        $newSnapshot = DataSnapshot::where('run_id', $failedRun->id)->first();
        $this->assertNull($newSnapshot, 'No snapshot must be created for the failed run.');

        // Assert: no partial snapshot values leaked from the failed run
        $partialValues = DataSnapshotValue::where('snapshot_id', $failedRun->id ?? 0)->count();
        $this->assertSame(0, $partialValues, 'No snapshot values must be associated with the failed run.');

        // Assert: the prior snapshot values are byte-for-byte unchanged
        $postValues = DataSnapshotValue::where('snapshot_id', $priorSnapshotId)
            ->orderBy('section')
            ->orderBy('dimension_key')
            ->get()
            ->map(fn (DataSnapshotValue $v) => [
                'section' => $v->section,
                'dimension_key' => $v->dimension_key,
                'value' => $v->value,
            ])
            ->all();
        $this->assertSame($priorValues, $postValues, 'Prior snapshot values must be unchanged after the failed run.');

        // Assert: all consumers still report the prior version (no partial output)
        $expectedWindow = [
            'start' => $currentActive->window_start->toDateString(),
            'end' => $currentActive->window_end->toDateString(),
            'timezone' => 'Asia/Jakarta',
        ];
        $this->assertAllConsumersReportVersion($priorVersion, $expectedWindow);

        // Assert: only one snapshot is active across the entire system
        $activeCount = DataSnapshot::where('is_active', true)->count();
        $this->assertSame(1, $activeCount, 'Exactly one snapshot must be active.');

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------ //
    // Cycle 3 — concurrent scheduler and manual run conflict guard        //
    // ------------------------------------------------------------------ //

    public function test_concurrent_scheduler_and_manual_run_cannot_publish_a_competing_snapshot(): void
    {
        // Arrange: freeze Asia/Jakarta clock
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: seed source data
        $this->seedAllSectionFixtures();

        // Arrange: bind a fully-staged service
        $this->bindPipelineService();

        // Arrange: pre-create an active (running) pipeline run to simulate the
        // scheduler holding the publication/active-run lock.
        $activeRun = DataPipelineRun::create([
            'run_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'running',
            'pipeline_version' => 'v1',
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ]);

        // Act: scheduler command encounters an active run → silently skips
        $this->artisan('data:pipeline')
            ->expectsOutput('Pipeline run is skipped: another run is already active.');

        // Refresh the admin token while the clock is frozen so JWT expiry
        // (tied to real clock) does not cause a 401 during the manual-trigger assertion.
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');

        // Act: concurrent manual trigger → explicit conflict response
        $manualResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/admin/pipeline/manual-trigger');

        // Assert: manual trigger receives an explicit 409 conflict
        $manualResponse->assertStatus(409)
            ->assertJsonPath('status', 'conflict')
            ->assertJsonPath('message', 'A pipeline run is already active.');

        // Assert: exactly one active pipeline run exists (the pre-created one)
        $activeRuns = DataPipelineRun::whereIn('status', ['running', 'processing', 'active'])->count();
        $this->assertSame(1, $activeRuns, 'Exactly one active run must remain.');

        // Assert: no new completed or failed runs were created by the concurrent calls
        $newCompletedRuns = DataPipelineRun::where('status', 'completed')
            ->where('run_uuid', '!=', $activeRun->run_uuid)
            ->count();
        $this->assertSame(0, $newCompletedRuns, 'No new completed run must be created.');

        // Assert: no snapshots were published during the conflict
        $publishedSnapshots = DataSnapshot::where('status', 'published')->count();
        $this->assertSame(0, $publishedSnapshots, 'No snapshot must be published during the conflict.');

        // Now complete the active run to prove the scheduler's publication is the
        // only consumer-visible version when the lock is released.
        // (Simulate the scheduler completing its run.)
        $this->app->forgetInstance(DataPipelineService::class);
        $this->bindPipelineService();
        $completedRun = DataPipelineRun::create([
            'run_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ]);
        $activeSnapshot = DataSnapshot::create([
            'run_id' => $completedRun->id,
            'snapshot_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version' => 1,
            'status' => 'staged',
            'is_active' => false,
            'window_start' => '2026-08-15',
            'window_end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ]);
        DB::table('data_snapshots')->where('id', $activeSnapshot->id)->update([
            'status' => 'published',
            'is_active' => true,
            'published_at' => now(),
        ]);

        // Assert: exactly one active publication remains
        $activeCount = DataSnapshot::where('is_active', true)->count();
        $this->assertSame(1, $activeCount, 'Exactly one active publication must exist.');

        // Assert: consumers expose only the scheduler's complete version
        $expectedWindow = [
            'start' => '2026-08-15',
            'end' => '2026-09-13',
            'timezone' => 'Asia/Jakarta',
        ];
        $this->assertAllConsumersReportVersion(1, $expectedWindow);

        Carbon::setTestNow();
    }
}
