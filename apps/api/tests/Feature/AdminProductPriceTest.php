<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProductPriceTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'admin-product-test@example.com';
    private string $supplierEmail = 'supplier-product-test@example.com';

    private function loginAsAdmin(): string
    {
        User::factory()->admin()->create([
            'email'     => $this->adminEmail,
            'password'  => Hash::make('password123'),
        ]);

        return $this->postJson('/api/auth/login', [
            'email'    => $this->adminEmail,
            'password' => 'password123',
        ])->json('data.token');
    }

    private function loginAsSupplier(): string
    {
        User::factory()->supplier()->create([
            'email'    => $this->supplierEmail,
            'password' => Hash::make('password123'),
        ]);

        return $this->postJson('/api/auth/login', [
            'email'    => $this->supplierEmail,
            'password' => 'password123',
        ])->json('data.token');
    }

    // =====================================================================
    // 1) Admin Price Update
    // =====================================================================

    public function test_admin_can_update_product_price(): void
    {
        $token  = $this->loginAsAdmin();
        $product = Product::factory()->create(['price' => 10000.00]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 15000.00]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.price', '15000.00');

        $product->refresh();
        $this->assertEquals('15000.00', $product->price);
    }

    public function test_supplier_cannot_update_product_price(): void
    {
        $token   = $this->loginAsSupplier();
        $product = Product::factory()->create(['price' => 10000.00]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 15000.00]);

        $response->assertForbidden();
    }

    public function test_negative_price_is_rejected(): void
    {
        $token   = $this->loginAsAdmin();
        $product = Product::factory()->create(['price' => 10000.00]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => -100]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_zero_price_is_accepted(): void
    {
        $token   = $this->loginAsAdmin();
        $product = Product::factory()->create(['price' => 10000.00]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 0]);

        $response->assertOk()
            ->assertJsonPath('data.price', '0.00');

        $product->refresh();
        $this->assertEquals('0.00', $product->price);
    }

    public function test_missing_price_returns_validation_error(): void
    {
        $token   = $this->loginAsAdmin();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    // =====================================================================
    // 2) Price History
    // =====================================================================

    public function test_price_change_creates_history_record(): void
    {
        $token    = $this->loginAsAdmin();
        $admin    = User::where('email', $this->adminEmail)->first();
        $product  = Product::factory()->create(['price' => 10000.00]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 15000.00]);

        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $product->id,
            'old_price'  => 10000.00,
            'new_price'  => 15000.00,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_price_history_endpoint_returns_all_changes(): void
    {
        $token   = $this->loginAsAdmin();
        $admin   = User::where('email', $this->adminEmail)->first();
        $product = Product::factory()->create(['price' => 10000.00]);

        // 3 price changes
        ProductPriceHistory::create([
            'product_id' => $product->id, 'old_price' => 10000.00,
            'new_price'  => 12000.00,    'changed_by' => $admin->id,
            'changed_at' => now()->subDays(3),
        ]);
        ProductPriceHistory::create([
            'product_id' => $product->id, 'old_price' => 12000.00,
            'new_price'  => 14000.00,    'changed_by' => $admin->id,
            'changed_at' => now()->subDays(2),
        ]);
        ProductPriceHistory::create([
            'product_id' => $product->id, 'old_price' => 14000.00,
            'new_price'  => 15000.00,    'changed_by' => $admin->id,
            'changed_at' => now()->subDay(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/products/{$product->id}/prices");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'product_id', 'old_price', 'new_price', 'changed_by', 'changed_at'],
                    ],
                    'meta' => ['limit', 'cursor', 'has_more'],
                ],
            ])
            ->assertJsonCount(3, 'data.data');
    }

    public function test_supplier_cannot_access_price_history(): void
    {
        $token   = $this->loginAsSupplier();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/admin/products/{$product->id}/prices");

        $response->assertForbidden();
    }

    // =====================================================================
    // 3) Price Snapshot at Order Creation + Cancel-New
    // =====================================================================

    public function test_price_snapshot_frozen_at_order_creation(): void
    {
        $token = $this->loginAsAdmin();

        // Create outlet + outlet user
        $outletUser = User::factory()->outlet()->create([
            'password' => Hash::make('password123'),
        ]);
        $outlet = \App\Models\Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create(['price' => 10000.00, 'stock_quantity' => 50]);
        $idempotencyKey = 'test-idempotency-' . uniqid();

        // Create order via API (outlet user)
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email, 'password' => 'password123',
        ])->json('data.token');

        $createResponse = $this->withHeader('Authorization', "Bearer {$outletToken}")
            ->postJson('/api/orders', [
                'idempotency_key' => $idempotencyKey,
                'items'           => [['product_id' => $product->id, 'quantity' => 2]],
            ]);
        $createResponse->assertStatus(201);
        $order = Order::find($createResponse->json('data.id'));
        $this->assertNotNull($order);
        $this->assertEquals(10000.00, $order->items->first()->unit_price);

        // Admin changes price to 15000
        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 15000.00])
            ->assertOk();

        // Admin approves order — unit_price must NOT change
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$order->id}/approve")
            ->assertOk();

        $order->refresh();
        $this->assertEquals(10000.00, $order->items->first()->unit_price);
    }

    public function test_cancel_new_order_without_invoice_succeeds(): void
    {
        $token = $this->loginAsAdmin();

        // Create outlet + outlet user
        $outletUser = User::factory()->outlet()->create([
            'password' => Hash::make('password123'),
        ]);
        $outlet = \App\Models\Outlet::factory()->create(['user_id' => $outletUser->id]);
        $outletUser->update(['outlet_id' => $outlet->id]);

        $product = Product::factory()->create(['price' => 10000.00, 'stock_quantity' => 50]);
        $idempotencyKey = 'test-cancel-' . uniqid();

        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email, 'password' => 'password123',
        ])->json('data.token');

        // Create New order via outlet user
        $createResponse = $this->withHeader('Authorization', "Bearer {$outletToken}")
            ->postJson('/api/orders', [
                'idempotency_key' => $idempotencyKey,
                'items'           => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $createResponse->assertStatus(201);
        $order = Order::find($createResponse->json('data.id'));
        $this->assertNotNull($order);
        $this->assertEquals('New', $order->status);

        // No invoice should exist
        $this->assertDatabaseMissing('invoices', ['order_id' => $order->id]);

        // Admin cancels — must succeed without invoice
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $order->refresh();
        $this->assertEquals('Cancelled', $order->status);
    }

    public function test_same_price_update_is_noop(): void
    {
        $token   = $this->loginAsAdmin();
        $product = Product::factory()->create(['price' => 10000.00]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/products/{$product->id}", ['price' => 10000.00]);

        // No history row for no-op update
        $this->assertDatabaseMissing('product_price_histories', [
            'product_id' => $product->id,
        ]);
    }
}
