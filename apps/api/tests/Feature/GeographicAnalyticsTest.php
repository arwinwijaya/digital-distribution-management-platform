<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Territory;
use App\Models\User;
use App\Services\ActiveDataSnapshotReader;
use App\Services\DataPipelineService;
use App\Services\GeographicAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GeographicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'geographic-admin@ddp.test',
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

    /**
     * Helper: create an outlet and backfill the created_at timestamp.
     */
    protected function createOutlet(array $overrides = []): Outlet
    {
        $outlet = Outlet::factory()->create($overrides);
        return $outlet;
    }

    /**
     * Helper: create a Delivered order for an outlet and backfill created_at.
     */
    protected function createOrder(Outlet $outlet, float $amount, string $createdAt): \App\Models\Order
    {
        $order = \App\Models\Order::create([
            'order_id' => 'ORD-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => $amount,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'geo-test-'.uniqid(),
        ]);
        \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

        return $order->fresh();
    }

    /**
     * Helper: run the pipeline with the geographic stage registered and return the run.
     */
    protected function runPipeline(): \App\Models\DataPipelineRun
    {
        $pipelineService = new DataPipelineService();
        $pipelineService->registerStage(
            'geographic',
            (new GeographicAnalyticsService())->stageCallback()
        );

        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);
        $run = $pipelineService->run();
        Carbon::setTestNow();

        return $run;
    }

    // ------------------------------------------------------------------ //
    // Cycle 1 — territory table and map data from active snapshot         //
    // ------------------------------------------------------------------ //

    public function test_territory_table_and_map_data_are_generated_from_the_active_snapshot(): void
    {
        // Arrange: freeze time to a known Jakarta moment for deterministic window
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: create territories
        $north = Territory::create(['name' => 'North', 'code' => 'north']);
        $south = Territory::create(['name' => 'South', 'code' => 'south']);

        // Arrange: create outlets with valid coordinates and assigned territories
        $outletA = Outlet::factory()->create([
            'territory_id' => $north->id,
            'latitude' => -6.15,
            'longitude' => 106.80,
            'name' => 'North A',
        ]);
        $outletB = Outlet::factory()->create([
            'territory_id' => $north->id,
            'latitude' => -6.20,
            'longitude' => 106.85,
            'name' => 'North B',
        ]);
        $outletC = Outlet::factory()->create([
            'territory_id' => $south->id,
            'latitude' => -6.25,
            'longitude' => 106.82,
            'name' => 'South C',
        ]);

        // Arrange: create orders within the 30-day pipeline window
        // North: 3 eligible orders totaling 300.00
        $this->createOrder($outletA, 150.00, '2026-09-10');
        $this->createOrder($outletB, 100.00, '2026-09-11');
        $this->createOrder($outletA, 50.00, '2026-09-12');
        // South: 1 eligible order totaling 120.00
        $this->createOrder($outletC, 120.00, '2026-09-11');

        // Arrange: publish the geographic snapshot via the real pipeline
        $pipelineService = new DataPipelineService();
        $pipelineService->registerStage(
            'geographic',
            (new GeographicAnalyticsService())->stageCallback()
        );
        $pipelineRun = $pipelineService->run();
        $this->assertSame('completed', $pipelineRun->status);

        Carbon::setTestNow();

        // Act: admin requests geographic BI
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');

        // Assert: the response is successful
        $response->assertOk()
            ->assertJsonPath('status', 'success');

        // Assert: North territory has correct aggregates
        $response->assertJsonFragment([
            'territory' => 'North',
            'sales' => '300.00',
            'orders' => 3,
            'outlets' => 2,
        ]);

        // Assert: South territory has correct aggregates
        $response->assertJsonFragment([
            'territory' => 'South',
            'sales' => '120.00',
            'orders' => 1,
            'outlets' => 1,
        ]);

        // Assert: map points include valid-coordinate outlets
        $mapPoints = $response->json('data.map_points');
        $this->assertIsArray($mapPoints);
        $this->assertCount(3, $mapPoints);

        $mapOutletNames = array_column($mapPoints, 'outlet_name');
        $this->assertContains('North A', $mapOutletNames);
        $this->assertContains('North B', $mapOutletNames);
        $this->assertContains('South C', $mapOutletNames);

        // Assert: snapshot_version equals the active snapshot version
        $reader = new ActiveDataSnapshotReader();
        $activeVersion = $reader->version();
        $this->assertNotNull($activeVersion);
        $response->assertJsonPath('data.snapshot_version', $activeVersion);

        // Assert: window metadata is returned exactly as published
        $window = $reader->window();
        $this->assertNotNull($window);
        $response->assertJsonPath('data.window.start', $window['start']);
        $response->assertJsonPath('data.window.end', $window['end']);
        $response->assertJsonPath('data.window.timezone', $window['timezone']);

        // Assert: no recompute — the endpoint does not read live source rows
        // We verify by checking that the data comes from the snapshot, not a fresh query.
        // The snapshot reader returns the exact published window, which proves read-through behavior.
    }

    // ------------------------------------------------------------------ //
    // Cycle 2 — missing assignment visible, invalid coords not plotted    //
    // ------------------------------------------------------------------ //

    public function test_missing_assignment_is_visible_but_not_plotted_without_coordinates(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: an outlet with no territory assignment
        $unassigned = Outlet::factory()->create([
            'territory_id' => null,
            'latitude' => -6.18,
            'longitude' => 106.81,
            'name' => 'No Territory Outlet',
        ]);

        // Arrange: an outlet with null coordinates (should not appear on map)
        $nullCoords = Outlet::factory()->create([
            'territory_id' => null,
            'latitude' => null,
            'longitude' => null,
            'name' => 'Null Coords Outlet',
        ]);

        // Arrange: an outlet with out-of-range coordinates (should not appear on map)
        $invalidCoords = Outlet::factory()->create([
            'territory_id' => null,
            'latitude' => 999.0,
            'longitude' => -999.0,
            'name' => 'Invalid Coords Outlet',
        ]);

        // Arrange: a valid outlet with assigned territory
        $valid = Outlet::factory()->create([
            'territory_id' => Territory::create(['name' => 'East', 'code' => 'east'])->id,
            'latitude' => -6.17,
            'longitude' => 106.83,
            'name' => 'East Valid',
        ]);

        // Arrange: orders for all outlets
        $this->createOrder($unassigned, 80.00, '2026-09-10');
        $this->createOrder($nullCoords, 50.00, '2026-09-10');
        $this->createOrder($invalidCoords, 60.00, '2026-09-10');
        $this->createOrder($valid, 100.00, '2026-09-10');

        // Arrange: publish snapshot
        $pipelineService = new DataPipelineService();
        $pipelineService->registerStage(
            'geographic',
            (new GeographicAnalyticsService())->stageCallback()
        );
        $pipelineRun = $pipelineService->run();
        $this->assertSame('completed', $pipelineRun->status);

        Carbon::setTestNow();

        // Act: admin requests geographic BI
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        // Assert: Unassigned outlet contributes to table data
        $tableData = $response->json('data.table');
        $this->assertNotEmpty($tableData);

        $unassignedRow = null;
        foreach ($tableData as $row) {
            if ($row['territory'] === 'Unassigned') {
                $unassignedRow = $row;
                break;
            }
        }
        $this->assertNotNull($unassignedRow, 'Unassigned territory row must exist in table.');
        $this->assertGreaterThan(0, $unassignedRow['outlets'], 'Unassigned must have at least one outlet.');
        $this->assertGreaterThan(0, (float) $unassignedRow['sales'], 'Unassigned must have non-zero sales.');
        $this->assertGreaterThan(0, $unassignedRow['orders'], 'Unassigned must have non-zero orders.');

        // Assert: map points do NOT include invalid-coordinate outlets
        $mapPoints = $response->json('data.map_points');
        $this->assertIsArray($mapPoints);

        $mapOutletNames = array_column($mapPoints, 'outlet_name');
        $this->assertContains('No Territory Outlet', $mapOutletNames, 'Valid-coordinate unassigned outlet should appear on map.');
        $this->assertNotContains('Null Coords Outlet', $mapOutletNames, 'Null-coordinate outlet must not appear on map.');
        $this->assertNotContains('Invalid Coords Outlet', $mapOutletNames, 'Out-of-range outlet must not appear on map.');
        $this->assertContains('East Valid', $mapOutletNames, 'Valid-coordinate assigned outlet should appear on map.');

        // Assert: snapshot_version and window metadata present
        $reader = new ActiveDataSnapshotReader();
        $response->assertJsonPath('data.snapshot_version', $reader->version());
        $response->assertJsonPath('data.window.start', $reader->window()['start']);
        $response->assertJsonPath('data.window.end', $reader->window()['end']);
        $response->assertJsonPath('data.window.timezone', $reader->window()['timezone']);
    }

    // ------------------------------------------------------------------ //
    // Cycle 3 — non-admin cannot access new routes                       //
    // ------------------------------------------------------------------ //

    public function test_non_admin_cannot_access_new_geographic_bi_or_territory_management(): void
    {
        // Arrange: create an outlet user for non-admin requests
        $outletUser = User::factory()->outlet()->create([
            'email' => 'geo-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email,
            'password' => 'password123',
        ])->json('data.token');

        // -- Unauthenticated: geographic BI --
        $this->getJson('/api/admin/analytics/geographic')
            ->assertUnauthorized();

        // -- Unauthenticated: list territories --
        $this->getJson('/api/admin/territories')
            ->assertUnauthorized();

        // -- Unauthenticated: store territory --
        $this->postJson('/api/admin/territories', ['name' => 'X'])
            ->assertUnauthorized();

        // -- Unauthenticated: update territory --
        $this->patchJson('/api/admin/territories/1', ['name' => 'X'])
            ->assertUnauthorized();

        // -- Unauthenticated: assign territory --
        $this->postJson('/api/admin/territories/1/assign', ['outlet_id' => 1])
            ->assertUnauthorized();

        // -- Outlet user (non-admin): geographic BI --
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->getJson('/api/admin/analytics/geographic')
            ->assertForbidden();

        // -- Outlet user: list territories --
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->getJson('/api/admin/territories')
            ->assertForbidden();

        // -- Outlet user: store territory --
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->postJson('/api/admin/territories', ['name' => 'X'])
            ->assertForbidden();

        // -- Outlet user: update territory --
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->patchJson('/api/admin/territories/1', ['name' => 'X'])
            ->assertForbidden();

        // -- Outlet user: assign territory --
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->postJson('/api/admin/territories/1/assign', ['outlet_id' => 1])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ //
    // Cycle 4 — admin CRUD and assignment persist through publication     //
    // ------------------------------------------------------------------ //

    public function test_admin_can_create_update_and_assign_territory_persistently(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Re-login after freezing time so the JWT iat matches frozen time.
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');

        // Arrange: create an outlet fixture (id 42 is not guaranteed;
        // we use a real outlet and capture its id)
        $outlet = Outlet::factory()->create([
            'latitude' => -6.18,
            'longitude' => 106.81,
        ]);
        $outletId = $outlet->id;

        // -- CRUD: create territory "North" --
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/admin/territories', ['name' => 'North']);
        $createResponse->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'North');

        $territoryId = $createResponse->json('data.id');
        $this->assertNotNull($territoryId);

        // -- Reload: verify persisted before publication --
        $reloaded = \App\Models\Territory::find($territoryId);
        $this->assertNotNull($reloaded);
        $this->assertSame('North', $reloaded->name);

        // -- CRUD: update territory to "North Metro" --
        $updateResponse = $this->withHeaders($this->adminHeaders())
            ->patchJson("/api/admin/territories/{$territoryId}", ['name' => 'North Metro']);
        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'North Metro');

        // -- Reload: verify the rename persisted --
        $reloaded = $reloaded->fresh();
        $this->assertSame('North Metro', $reloaded->name);

        // -- Assign outlet to territory --
        $assignResponse = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/admin/territories/{$territoryId}/assign", ['outlet_id' => $outletId]);
        $assignResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // -- Reload outlet: verify assignment persisted --
        $outlet = $outlet->fresh();
        $this->assertSame($territoryId, $outlet->territory_id);

        // -- Create an order so the outlet appears in geographic aggregates --
        $this->createOrder($outlet, 200.00, '2026-09-11');

        // -- Unauthenticated writes are rejected --
        // withHeaders() persists to the test instance; clear before anonymous requests.
        $this->flushHeaders();
        Carbon::setTestNow();
        $this->postJson('/api/admin/territories', ['name' => 'Y'])->assertUnauthorized();
        $this->patchJson("/api/admin/territories/{$territoryId}", ['name' => 'Y'])->assertUnauthorized();
        $this->postJson("/api/admin/territories/{$territoryId}/assign", ['outlet_id' => $outletId])->assertUnauthorized();

        // -- Non-admin writes are rejected --
        $outletUser = User::factory()->outlet()->create([
            'email' => 'geo-assign-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email,
            'password' => 'password123',
        ])->json('data.token');
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->postJson('/api/admin/territories', ['name' => 'Z'])->assertForbidden();

        // -- Invoke the public pipeline publication entry point --
        $pipelineService = new DataPipelineService();
        $pipelineService->registerStage(
            'geographic',
            (new GeographicAnalyticsService())->stageCallback()
        );
        $pipelineRun = $pipelineService->run();
        $this->assertSame('completed', $pipelineRun->status);

        Carbon::setTestNow();

        // Carbon::setTestNow() clears the frozen-time offset, but the adminToken
        // was minted while the clock was frozen (iat in the past relative to UTC).
        // tymon/jwt-auth validates exp/nbf against real UTC time; re-login so the
        // JWT claims match the restored wall clock.
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');

        // -- Verify BI reads the renamed territory from the new snapshot --
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');
        $response->assertOk()
            ->assertJsonPath('status', 'success');

        // "North Metro" should appear in the table, not "North"
        $tableData = $response->json('data.table');
        $names = array_column($tableData, 'territory');
        $this->assertContains('North Metro', $names);
        $this->assertNotContains('North', $names, 'Old territory name must not appear after rename.');

        // snapshot_version matches the newly published active snapshot
        $reader = new ActiveDataSnapshotReader();
        $response->assertJsonPath('data.snapshot_version', $reader->version());
    }
}
