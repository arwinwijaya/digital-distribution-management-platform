<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Territory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function bearerFor(User $user): string
    {
        $token = app(\App\Services\AuthService::class)->createToken($user)['token'];

        return 'Bearer ' . $token;
    }

    private function makeTerritory(string $code): Territory
    {
        return Territory::create(['name' => "Territory {$code}", 'code' => $code]);
    }

    private function makeSalesUser(?int $territoryId): User
    {
        return User::factory()->sales()->create([
            'is_active'    => true,
            'territory_id' => $territoryId,
        ]);
    }

    private function payload(int $outletId, Product $product, int $quantity = 1): array
    {
        return [
            'outlet_id'       => $outletId,
            'items'           => [['product_id' => $product->id, 'quantity' => $quantity]],
            'idempotency_key' => 'sales-order-key-' . uniqid(),
        ];
    }

    // =================================================================
    // Territory binding
    // =================================================================

    /**
     * GWT: Given sales user with territory_id=T, When creating order for
     * outlet in territory T, Then 201 with sales_user_id recorded.
     */
    public function test_sales_user_can_create_order_for_outlet_in_own_territory(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);
        $product = Product::factory()->create(['price' => 15000, 'stock_quantity' => 10]);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product, 2));

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.sales_user_id', $sales->id)
            ->assertJsonPath('data.outlet_id', $outlet->id)
            ->assertJsonPath('data.status', 'New')
            ->assertJsonPath('data.total_amount', '30000.00');

        $this->assertDatabaseHas('orders', [
            'id'            => $response->json('data.id'),
            'outlet_id'     => $outlet->id,
            'sales_user_id' => $sales->id,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
    }

    /**
     * GWT: Given sales user with territory_id=T, When creating order for
     * outlet in territory U, Then 403.
     */
    public function test_sales_user_cannot_create_order_outside_territory(): void
    {
        $ownTerritory = $this->makeTerritory('T1');
        $otherTerritory = $this->makeTerritory('U1');
        $sales = $this->makeSalesUser($ownTerritory->id);
        $outlet = Outlet::factory()->create(['territory_id' => $otherTerritory->id]);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(403);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    /**
     * GWT: Given sales user with territory_id=null, When creating any order,
     * Then 403 (must be assigned).
     */
    public function test_sales_user_without_territory_cannot_create_order(): void
    {
        $sales = $this->makeSalesUser(null);
        $outlet = Outlet::factory()->create([
            'territory_id' => $this->makeTerritory('T1')->id,
        ]);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(403);

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * GWT: Given sales user with territory_id=T, When the outlet has no
     * territory, Then 403 (cannot prove binding).
     */
    public function test_sales_user_cannot_create_order_for_untagged_outlet(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $outlet = Outlet::factory()->create(['territory_id' => null]);
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(403);

        $this->assertDatabaseCount('orders', 0);
    }

    // =================================================================
    // Validation / authorization boundaries
    // =================================================================

    public function test_sales_order_requires_outlet_id(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $product = Product::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_id']);
    }

    public function test_sales_order_rejects_unknown_outlet(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $product = Product::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', [
                'outlet_id' => 999999,
                'items'     => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_id']);
    }

    public function test_sales_order_requires_items(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', ['outlet_id' => $outlet->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * GWT: Given non-sales roles, When using POST /sales/orders, Then 403.
     */
    public function test_outlet_user_cannot_create_sales_order(): void
    {
        $territory = $this->makeTerritory('T1');
        $outletUser = User::factory()->outlet()->create(['is_active' => true]);
        $outlet = Outlet::factory()->create([
            'user_id'      => $outletUser->id,
            'territory_id' => $territory->id,
        ]);
        $product = Product::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($outletUser))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(403);
    }

    public function test_admin_cannot_create_sales_order_through_sales_endpoint(): void
    {
        $territory = $this->makeTerritory('T1');
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);
        $product = Product::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_sales_order(): void
    {
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $this->postJson('/api/sales/orders', $this->payload($outlet->id, $product))
            ->assertStatus(401);
    }

    // =================================================================
    // Shared pipeline reuse
    // =================================================================

    /**
     * The sales endpoint must reuse the OrderCreationService idempotency
     * contract: replaying the same identity returns the original order (200)
     * and never creates a second order.
     */
    public function test_sales_order_is_idempotent_for_same_identity(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 10]);
        $payload = $this->payload($outlet->id, $product, 1);

        $first = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $payload);
        $second = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', $payload);

        $first->assertCreated();
        $second->assertOk();
        $this->assertSame($first->json('data.order_id'), $second->json('data.order_id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 9]);
    }

    /**
     * Promotion application must flow through the same OrderCreationService
     * pipeline (no forked order-creation path).
     */
    public function test_sales_order_applies_promotion_through_shared_pipeline(): void
    {
        $territory = $this->makeTerritory('T1');
        $sales = $this->makeSalesUser($territory->id);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);
        $product = Product::factory()->create(['price' => 20000, 'stock_quantity' => 10]);

        $promo = Promotion::factory()->create([
            'product_id'     => $product->id,
            'discount_type'  => 'fixed',
            'discount_value' => 5000,
            'min_order'      => 0,
            'is_active'      => true,
            'start_date'     => now()->subWeek()->format('Y-m-d'),
            'end_date'       => now()->addWeek()->format('Y-m-d'),
        ]);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/sales/orders', [
                'outlet_id'       => $outlet->id,
                'items'           => [['product_id' => $product->id, 'quantity' => 2]],
                'promotion_id'    => $promo->id,
                'idempotency_key' => 'sales-order-promo-' . uniqid(),
            ]);

        $response->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $this->assertSame($sales->id, $order->sales_user_id);
        $this->assertSame($promo->id, $order->promotion_id);
        $this->assertEquals('35000.00', $order->total_amount);
        $this->assertEquals('5000.00', $order->discount_amount);
    }
}
