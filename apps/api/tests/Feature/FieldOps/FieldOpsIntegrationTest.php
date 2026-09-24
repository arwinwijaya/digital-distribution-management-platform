<?php

namespace Tests\Feature\FieldOps;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\SalesVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

/**
 * Phase 8 — T18: cross-unit integration verification.
 *
 * End-to-end field-ops flow: sales check-in → driver ping → PoD upload → admin track.
 * Verifies state consistency across units: SalesVisit, Delivery, DeliveryLocationPing, PoD metadata.
 */
class FieldOpsIntegrationTest extends TestCase
{
    use RefreshDatabase, DeliveryTestFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function photo(int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->image('proof.jpg', 800, 600)->size($kb);
    }

    private function signature(int $kb = 40): UploadedFile
    {
        return UploadedFile::fake()->image('signature.png', 400, 200)->size($kb);
    }

    /** @test End-to-end: visit check-in → driver ping → PoD upload → admin track returns path. */
    public function test_full_field_ops_flow_checkin_ping_pod_track(): void
    {
        // ── 1. Create sales user and an outlet with coordinates ──
        $sales = User::factory()->sales()->create([
            'email' => 'integration-sales@example.com',
            'password' => Hash::make('password'),
        ]);
        $outlet = Outlet::factory()->create([
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ]);

        // ── 2. Sales creates a visit and checks in within radius ──
        $visit = SalesVisit::create([
            'sales_user_id' => $sales->id,
            'outlet_id' => $outlet->id,
            'visit_date' => now()->toDateString(),
            'status' => 'planned',
        ]);

        $salesToken = $this->postJson('/api/auth/login', [
            'email' => $sales->email,
            'password' => 'password',
        ])->json('data.token');

        $checkInResponse = $this->withHeader('Authorization', 'Bearer '.$salesToken)
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000500,
                'longitude' => 106.8167000,
                'accuracy_m' => 12,
            ]);

        $checkInResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.check_in_latitude', '-6.2000500');

        $visit->refresh();
        $this->assertNotNull($visit->check_in_at);
        $this->assertSame('-6.2000500', $visit->check_in_latitude);

        // ── 3. Create driver + admin + delivery (assigned → in_progress) ──
        ['driver' => $driver, 'delivery' => $delivery, 'admin' => $admin] = $this->createDeliveryFixture('ORD-INT-1', 'int-1', Delivery::IN_PROGRESS, true);

        $driverToken = $this->loginAsDeliveryUser($driver);

        // ── 4. Driver pings location (while en route) ──
        $pingResponse = $this->withHeader('Authorization', 'Bearer '.$driverToken)
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2001000,
                'longitude' => 106.8167500,
                'accuracy_m' => 8,
            ]);

        $pingResponse->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.latitude', '-6.2001000');

        // Second ping later in the trip
        $pingResponse2 = $this->withHeader('Authorization', 'Bearer '.$driverToken)
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2002000,
                'longitude' => 106.8168000,
                'accuracy_m' => 6,
            ]);
        $pingResponse2->assertCreated();

        // ── 5. Driver uploads PoD at delivery site ──
        $podResponse = $this->withHeader('Authorization', 'Bearer '.$driverToken)
            ->post("/api/deliveries/{$delivery->id}/proof", [
                'photo' => $this->photo(),
                'signature' => $this->signature(),
                'latitude' => -6.2003000,
                'longitude' => 106.8168500,
            ]);

        $podResponse->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.proof_of_delivery.photo_url', fn ($u) => is_string($u) && $u !== '')
            ->assertJsonPath('data.proof_of_delivery.signature_url', fn ($u) => is_string($u) && $u !== '');

        $delivery->refresh();
        $this->assertNotNull($delivery->pod_captured_at);
        $this->assertSame('-6.2003000', $delivery->pod_latitude);
        $this->assertSame('106.8168500', $delivery->pod_longitude);

        // ── 6. Admin reads live track → should see both pings, last_position = newest ping ──
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        $trackResponse = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/admin/deliveries/{$delivery->id}/track");

        $trackResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.delivery_id', $delivery->id)
            ->assertJsonPath('data.driver.id', $driver->id)
            ->assertJsonPath('data.last_position.latitude', '-6.2002000')
            ->assertJsonPath('data.last_position.longitude', '106.8168000')
            ->assertJsonPath('data.pings.0.latitude', '-6.2002000'); // newest first

        // Verify pings list contains both driver pings (newest first) and PoD is NOT a ping
        $pingsData = $trackResponse->json('data.pings');
        $this->assertCount(2, $pingsData);
        $this->assertSame('-6.2002000', $pingsData[0]['latitude']); // 2nd ping (newest)
        $this->assertSame('-6.2001000', $pingsData[1]['latitude']); // 1st ping (older)

        // ── 7. Cross-verify: route endpoint returns route_data from T9 ──
        $routeResponse = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/admin/deliveries/{$delivery->id}/route");

        $routeResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.route_data', fn ($rd) => is_array($rd) && isset($rd['stops']));
    }

    /** @test sales check-out after delivery does not affect delivery track. */
    public function test_sales_checkout_independent_of_delivery_track(): void
    {
        // Setup separate sales visit + delivery
        $sales = User::factory()->sales()->create([
            'email' => 'checkout-sales@example.com',
            'password' => Hash::make('password'),
        ]);
        $outlet = Outlet::factory()->create([
            'latitude' => -6.1800000,
            'longitude' => 106.8200000,
        ]);

        $visit = SalesVisit::create([
            'sales_user_id' => $sales->id,
            'outlet_id' => $outlet->id,
            'visit_date' => now()->toDateString(),
            'status' => 'planned',
        ]);

        $salesToken = $this->postJson('/api/auth/login', [
            'email' => $sales->email,
            'password' => 'password',
        ])->json('data.token');

        // Check in + check out
        $this->withHeader('Authorization', 'Bearer '.$salesToken)
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.1800100,
                'longitude' => 106.8200100,
                'accuracy_m' => 10,
            ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$salesToken)
            ->postJson("/api/sales/visits/{$visit->id}/check-out", [
                'latitude' => -6.1800200,
                'longitude' => 106.8200200,
                'accuracy_m' => 11,
            ])->assertOk();

        $visit->refresh();
        $this->assertNotNull($visit->check_out_at);
        $this->assertSame('completed', $visit->status);

        // Delivery track is unaffected
        ['driver' => $driver, 'delivery' => $delivery] = $this->createDeliveryFixture('ORD-INT-2', 'int-2', Delivery::IN_PROGRESS);
        $driverToken = $this->loginAsDeliveryUser($driver);

        $this->withHeader('Authorization', 'Bearer '.$driverToken)
            ->postJson("/api/deliveries/{$delivery->id}/location", [
                'latitude' => -6.2001000,
                'longitude' => 106.8167500,
                'accuracy_m' => 8,
            ])->assertCreated();
    }

    /** @test offline-like: driver pings multiple times, track limits to bounded history. */
    public function test_track_respects_ping_limit(): void
    {
        ['driver' => $driver, 'delivery' => $delivery, 'admin' => $admin] = $this->createDeliveryFixture('ORD-INT-3', 'int-3', Delivery::IN_PROGRESS);
        $driverToken = $this->loginAsDeliveryUser($driver);

        // Send TRACK_PING_LIMIT + 3 pings (constant is 50)
        for ($i = 0; $i < 53; $i++) {
            $this->withHeader('Authorization', 'Bearer '.$driverToken)
                ->postJson("/api/deliveries/{$delivery->id}/location", [
                    'latitude' => -6.2000000 + $i * 0.0001,
                    'longitude' => 106.8166667 + $i * 0.0001,
                    'accuracy_m' => 5 + $i,
                ])->assertCreated();
        }

        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        $trackResponse = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/admin/deliveries/{$delivery->id}/track");

        $trackResponse->assertOk();
        $pings = $trackResponse->json('data.pings');
        $this->assertCount(50, $pings); // bounded by TRACK_PING_LIMIT
    }
}