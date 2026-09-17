<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Helper: makes a JWT Bearer header for a given User (reuse by all tests).
    // -----------------------------------------------------------------
    private function bearerFor(User $user): string
    {
        $raw = app(\App\Services\AuthService::class)->createToken($user);
        $token = is_array($raw) ? ($raw['token'] ?? array_values($raw)[0]) : $raw;

        return 'Bearer ' . $token;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);

        return $user;
    }

    private function promotionPayload(array $overrides = []): array
    {
        // Use relative dates so "active" promos remain inside the 1-month window
        // even when CI's system date differs from assumption. Caller can set
        // explicit start/end via $overrides.
        return array_merge([
            'name'          => 'Ramadan 50% off',
            'description'   => 'All Ramadan promos',
            'discount_type' => 'percentage',
            'discount_value'=> 50,
            'max_discount'  => 100000,
            'min_order'     => 0,
            'start_date'    => now()->subMonth()->format('Y-m-d'),
            'end_date'      => now()->addMonth()->format('Y-m-d'),
        ], $overrides);
    }

    // =================================================================
    // CRUD
    // =================================================================

    public function test_admin_can_create_promotion(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $response = $this->withHeader('Authorization', $token)
            ->postJson('/api/admin/promotions', $this->promotionPayload());

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Ramadan 50% off')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('promotions', [
            'name'     => 'Ramadan 50% off',
            'is_active'=> true,
        ]);
    }

    public function test_admin_can_list_promotions_with_limit_plus_one(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        Promotion::factory()->count(5)->active()->create();

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?limit=2');

        // limit+1 technique: exactly `limit` rows returned, has_more signals a next page.
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.has_more', true);
    }

    /**
     * GWT: Given promotions with varied start/end/created_at, When GET
     * /api/admin/promotions?limit=15&cursor=0, Then data is created_at DESC
     * (id DESC tiebreak) and meta.total + meta.summary 4-state are consistent.
     */
    public function test_admin_list_promotions_defaults_to_newest_with_total_and_summary(): void
    {
        Carbon::setTestNow('2026-06-15 12:00:00');

        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        // Ended (id 1) — created March
        Promotion::factory()->create([
            'name' => 'Ended Promo',
            'start_date' => '2026-04-01',
            'end_date' => '2026-05-01',
            'created_at' => '2026-03-01 08:00:00',
        ]);
        // Active (id 2) — created January
        Promotion::factory()->create([
            'name' => 'Active Promo Old',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'created_at' => '2026-01-01 08:00:00',
        ]);
        // Scheduled (id 3) — created April
        Promotion::factory()->create([
            'name' => 'Scheduled Promo',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'created_at' => '2026-04-01 08:00:00',
        ]);
        // Active (id 4) — created February
        Promotion::factory()->create([
            'name' => 'Active Promo New',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
            'created_at' => '2026-02-01 08:00:00',
        ]);

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?limit=15&cursor=0');

        $response->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.summary.total', 4)
            ->assertJsonPath('meta.summary.active', 2)
            ->assertJsonPath('meta.summary.scheduled', 1)
            ->assertJsonPath('meta.summary.ended', 1)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.limit', 15)
            ->assertJsonPath('meta.cursor', 0)
            // created_at DESC: Apr, Mar, Feb, Jan (NOT id order).
            ->assertJsonPath('data.0.name', 'Scheduled Promo')
            ->assertJsonPath('data.1.name', 'Ended Promo')
            ->assertJsonPath('data.2.name', 'Active Promo New')
            ->assertJsonPath('data.3.name', 'Active Promo Old');

        $json = $response->json();
        $this->assertSame(
            $json['meta']['summary']['total'],
            $json['meta']['summary']['active']
                + $json['meta']['summary']['scheduled']
                + $json['meta']['summary']['ended'],
        );
        $this->assertArrayNotHasKey('next_cursor', $json);
    }

    /**
     * GWT: Given 20 promotions, When GET /api/admin/promotions?cursor=15, Then
     * the SECOND page is returned by OFFSET (not `where id > 15`).
     */
    public function test_admin_list_promotions_cursor_is_offset_based(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        // id 1 = oldest ... id 20 = newest, so created_at DESC order is id 20..1.
        for ($i = 1; $i <= 20; $i++) {
            Promotion::factory()->create([
                'name' => 'Promo ' . $i,
                'created_at' => now()->subDays(21 - $i),
            ]);
        }

        $page1 = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?limit=15&cursor=0');
        $page1->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.0.name', 'Promo 20')
            ->assertJsonPath('data.14.name', 'Promo 6');

        // Offset 15 => rows 16..20 of the sorted set (id-based would give 16..20 ids).
        $page2 = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?limit=15&cursor=15');
        $page2->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.cursor', 15)
            ->assertJsonPath('data.0.name', 'Promo 5')
            ->assertJsonPath('data.4.name', 'Promo 1');
    }

    /**
     * GWT: Given promotions with varied start_date, When GET
     * /api/admin/promotions?sort=start_date&order=asc, Then ordered by
     * start_date ASC (not the default created_at DESC).
     */
    public function test_admin_list_promotions_sorts_by_start_date_ascending(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        // created_at order intentionally the REVERSE of start_date order, so the
        // default (created_at DESC) differs from start_date ASC.
        Promotion::factory()->create(['name' => 'March', 'start_date' => '2026-03-01', 'created_at' => '2026-03-01 08:00:00']);
        Promotion::factory()->create(['name' => 'January', 'start_date' => '2026-01-01', 'created_at' => '2026-01-01 08:00:00']);
        Promotion::factory()->create(['name' => 'February', 'start_date' => '2026-02-01', 'created_at' => '2026-02-01 08:00:00']);

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?sort=start_date&order=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'January')
            ->assertJsonPath('data.1.name', 'February')
            ->assertJsonPath('data.2.name', 'March');
    }

    /**
     * GWT: Given a promotion with a null created_at, When sorting by created_at
     * asc or desc, Then the null row is always last.
     */
    public function test_admin_list_promotions_places_null_created_at_rows_last_in_both_directions(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        Promotion::factory()->create(['name' => 'Dated A', 'created_at' => '2026-02-01 08:00:00']);
        Promotion::factory()->create(['name' => 'Undated', 'created_at' => null]);
        Promotion::factory()->create(['name' => 'Dated B', 'created_at' => '2026-01-01 08:00:00']);

        $desc = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?sort=created_at&order=desc');
        $desc->assertOk()
            ->assertJsonPath('data.0.name', 'Dated A')
            ->assertJsonPath('data.1.name', 'Dated B')
            ->assertJsonPath('data.2.name', 'Undated');

        $asc = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?sort=created_at&order=asc');
        $asc->assertOk()
            ->assertJsonPath('data.0.name', 'Dated B')
            ->assertJsonPath('data.1.name', 'Dated A')
            ->assertJsonPath('data.2.name', 'Undated');
    }

    /**
     * GWT: Given an invalid sort param, When GET /api/admin/promotions, Then
     * HTTP 200 with the default newest-first order (never 422/500).
     */
    public function test_admin_list_promotions_silently_falls_back_for_invalid_sort(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        Promotion::factory()->create(['name' => 'Older', 'created_at' => '2026-01-01 08:00:00']);
        Promotion::factory()->create(['name' => 'Newer', 'created_at' => '2026-03-01 08:00:00']);

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?sort=__proto__&order=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    /**
     * GWT: Given non-scalar (array) sort/order/cursor params, When GET
     * /api/admin/promotions, Then HTTP 200 with defaults (never 500).
     */
    public function test_admin_list_promotions_handles_non_scalar_params_without_error(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        Promotion::factory()->create(['name' => 'Older', 'created_at' => '2026-01-01 08:00:00']);
        Promotion::factory()->create(['name' => 'Newer', 'created_at' => '2026-03-01 08:00:00']);

        $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?sort[]=x')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');

        $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?order[]=x')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');

        $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions?cursor[]=x')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Newer')
            ->assertJsonPath('data.1.name', 'Older');
    }

    public function test_admin_can_view_promotion(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $promo = Promotion::factory()->active()->create();

        $response = $this->withHeader('Authorization', $token)
            ->getJson("/api/admin/promotions/{$promo->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $promo->id);
    }

    public function test_admin_can_update_promotion(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $promo = Promotion::factory()->notYetBroadcast()->create();

        $response = $this->withHeader('Authorization', $token)
            ->patchJson("/api/admin/promotions/{$promo->id}", [
                'name' => 'Updated promo',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated promo');
    }

    public function test_admin_can_delete_non_broadcast_promotion(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $promo = Promotion::factory()->notYetBroadcast()->create();

        $response = $this->withHeader('Authorization', $token)
            ->deleteJson("/api/admin/promotions/{$promo->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
    }

    public function test_outlet_cannot_access_promotions_admin_routes(): void
    {
        // A normal outlet user must be denied (403 or 401), not 200.
        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);
        $token = $this->bearerFor($outletUser);

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/admin/promotions');

        $response->assertStatus(403);
    }

    public function test_promo_with_past_end_date_is_not_considered_active(): void
    {
        $promo = Promotion::factory()->create([
            'is_active'  => false,
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date'   => now()->subMonths(2)->format('Y-m-d'),
        ]);

        $this->assertFalse((bool) $promo->is_active);
    }

    // =================================================================
    // Overlap: single promo per product per time
    // =================================================================

    public function test_overlapping_promos_on_same_product_rejected(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);
        $product = Product::factory()->create();

        // P1: Sept 1-30 of the current month ± 1 month window
        $p1Payload = $this->promotionPayload([
            'product_id' => $product->id,
            'start_date' => now()->subWeek()->format('Y-m-d'),
            'end_date'   => now()->addWeek()->format('Y-m-d'),
        ]);
        $this->withHeader('Authorization', $token)
            ->postJson('/api/admin/promotions', $p1Payload)
            ->assertCreated();

        // P2: overlaps with P1 (same product, overlapping dates)
        $p2Payload = $this->promotionPayload([
            'product_id' => $product->id,
            'start_date' => now()->subDays(3)->format('Y-m-d'),
            'end_date'   => now()->addDays(14)->format('Y-m-d'),
        ]);
        $response = $this->withHeader('Authorization', $token)
            ->postJson('/api/admin/promotions', $p2Payload);

        $response->assertStatus(422);
    }

    public function test_non_overlapping_promos_on_same_product_both_valid(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);
        $product = Product::factory()->create();

        $p1Payload = $this->promotionPayload([
            'product_id' => $product->id,
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date'   => now()->subDays(10)->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', $token)
            ->postJson('/api/admin/promotions', $p1Payload)
            ->assertCreated();

        $p2Payload = $this->promotionPayload([
            'product_id' => $product->id,
            'start_date' => now()->addDays(10)->format('Y-m-d'),
            'end_date'   => now()->addMonth()->format('Y-m-d'),
        ]);

        $this->withHeader('Authorization', $token)
            ->postJson('/api/admin/promotions', $p2Payload)
            ->assertCreated();
    }

    // =================================================================
    // Min order threshold (promo applied at order creation)
    // =================================================================

    public function test_promo_not_applied_when_min_order_not_met(): void
    {
        // Create outlet, outlet user, products, and a promo with min_order
        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);
        $outlet = Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 100,
        ]);

        $promo = Promotion::factory()->create([
            'product_id'    => null,
            'discount_type' => 'fixed',
            'discount_value'=> 10000,
            'min_order'     => 999999, // unreachable threshold
            'is_active'     => true,
            'start_date'    => now()->subWeek()->format('Y-m-d'),
            'end_date'      => now()->addWeek()->format('Y-m-d'),
        ]);

        $outletToken = $this->bearerFor($outletUser);

        $response = $this->withHeader('Authorization', $outletToken)
            ->postJson('/api/orders', [
                'idempotency_key' => 'test-min-order-fail-' . uniqid(),
                'items'           => [['product_id' => $product->id, 'quantity' => 1]],
                'promotion_id'    => $promo->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['promotion_id']);
    }

    public function test_promo_applied_when_min_order_met(): void
    {
        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);
        $outlet = Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create([
            'price' => 10000,
            'stock_quantity' => 100,
        ]);

        $promo = Promotion::factory()->create([
            'product_id'    => $product->id,
            'discount_type' => 'fixed',
            'discount_value'=> 10000,
            'min_order'     => 0,
            'is_active'     => true,
            'start_date'    => now()->subWeek()->format('Y-m-d'),
            'end_date'      => now()->addWeek()->format('Y-m-d'),
        ]);

        $outletToken = $this->bearerFor($outletUser);

        $response = $this->withHeader('Authorization', $outletToken)
            ->postJson('/api/orders', [
                'idempotency_key' => 'test-min-order-ok-' . uniqid(),
                'items'           => [['product_id' => $product->id, 'quantity' => 1]],
                'promotion_id'    => $promo->id,
            ]);

        $response->assertCreated();

        $order = Order::find($response->json('data.id'));
        $this->assertNotNull($order);
        $this->assertEquals($promo->id, $order->promotion_id);
        $this->assertGreaterThan(0, (float) $order->discount_amount);
        // total_amount is the post-discount payable total.
        $this->assertEquals('0.00', $order->total_amount);
    }

    // =================================================================
    // Promo snapshot at order creation (immutability)
    // =================================================================

    public function test_promo_snapshot_frozen_at_order_creation(): void
    {
        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);
        $outlet = Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create([
            'price' => 20000,
            'stock_quantity' => 100,
        ]);

        $promo = Promotion::factory()->create([
            'product_id'    => $product->id,
            'discount_type' => 'fixed',
            'discount_value'=> 5000,
            'min_order'     => 0,
            'is_active'     => true,
            'start_date'    => now()->subWeek()->format('Y-m-d'),
            'end_date'      => now()->addWeek()->format('Y-m-d'),
        ]);

        $outletToken = $this->bearerFor($outletUser);

        $response = $this->withHeader('Authorization', $outletToken)
            ->postJson('/api/orders', [
                'idempotency_key' => 'test-snapshot-' . uniqid(),
                'items'           => [['product_id' => $product->id, 'quantity' => 2]],
                'promotion_id'    => $promo->id,
            ]);

        $response->assertCreated();
        $order = Order::find($response->json('data.id'));
        $snapshotDiscount = $order->discount_amount;

        // Deactivate the promo after order creation.
        $promo->update(['is_active' => false]);
        $order->refresh();

        // Order keeps the original snapshot.
        $this->assertEquals($snapshotDiscount, $order->discount_amount);
        $this->assertEquals($promo->id, $order->promotion_id);
    }

    public function test_promo_created_after_order_does_not_auto_apply(): void
    {
        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password'  => Hash::make('password123'),
        ]);
        $outlet = Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create([
            'price' => 20000,
            'stock_quantity' => 100,
        ]);

        $outletToken = $this->bearerFor($outletUser);

        $response = $this->withHeader('Authorization', $outletToken)
            ->postJson('/api/orders', [
                'idempotency_key' => 'test-no-retro-' . uniqid(),
                'items'           => [['product_id' => $product->id, 'quantity' => 1]],
            ]);

        $response->assertCreated();
        $order = Order::find($response->json('data.id'));
        $this->assertNull($order->promotion_id);
        $this->assertEquals('0.00', $order->discount_amount);

        // Retroactive promo must not appear on the already-created order.
        Promotion::factory()->create([
            'product_id'    => $product->id,
            'discount_type' => 'percentage',
            'discount_value'=> 50,
            'min_order'     => 0,
            'is_active'     => true,
            'start_date'    => now()->subWeek()->format('Y-m-d'),
            'end_date'      => now()->addWeek()->format('Y-m-d'),
        ]);

        $order->refresh();
        $this->assertNull($order->promotion_id);
    }

    // =================================================================
    // Immutability after broadcast
    // =================================================================

    public function test_broadcast_promo_update_is_blocked(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $promo = Promotion::factory()->broadcast()->create();

        $response = $this->withHeader('Authorization', $token)
            ->patchJson("/api/admin/promotions/{$promo->id}", [
                'name' => 'Edited after broadcast',
            ]);

        $response->assertStatus(422);
    }

    public function test_non_broadcast_promo_can_still_be_updated(): void
    {
        $admin = $this->makeAdmin();
        $token = $this->bearerFor($admin);

        $promo = Promotion::factory()->notYetBroadcast()->create();

        $response = $this->withHeader('Authorization', $token)
            ->patchJson("/api/admin/promotions/{$promo->id}", [
                'name' => 'Edited before broadcast',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Edited before broadcast');
    }
}
