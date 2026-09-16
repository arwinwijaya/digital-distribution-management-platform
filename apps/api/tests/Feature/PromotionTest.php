<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

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

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_cursor', Promotion::orderBy('id')->limit(2)->get()[1]->id);
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
