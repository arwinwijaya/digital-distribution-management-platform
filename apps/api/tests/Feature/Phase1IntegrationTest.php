<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase1IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_onboarding_to_admin_approval_has_no_manual_association(): void
    {
        $product = Product::factory()->create([
            'price' => 12000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create([
            'email' => 'phase1-admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $registration = $this->postJson('/api/auth/register', [
            'name' => 'Phase 1 Outlet',
            'email' => 'phase1-outlet@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '081234567890',
            'address' => 'Jl. Phase 1',
            'city' => 'Jakarta',
            'district' => 'Menteng',
        ])->assertCreated();

        $this->assertDatabaseHas('outlets', [
            'phone' => '081234567890',
            'user_id' => $registration->json('data.user.id'),
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'phase1-outlet@example.com',
            'password' => 'password123',
        ])->assertOk();
        $outletHeaders = ['Authorization' => 'Bearer '.$login->json('data.token')];

        $this->withHeaders($outletHeaders)->getJson('/api/products')->assertOk();
        $order = $this->withHeaders($outletHeaders)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'idempotency_key' => 'phase1-cross-task-order',
        ])->assertCreated();

        $adminLogin = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertOk();
        $adminHeaders = ['Authorization' => 'Bearer '.$adminLogin->json('data.token')];
        $orderId = $order->json('data.id');

        $this->withHeaders($adminHeaders)->getJson('/api/admin/orders/'.$orderId)->assertOk();
        $this->withHeaders($adminHeaders)->putJson('/api/orders/'.$orderId.'/approve')->assertOk();
        $this->withHeaders($outletHeaders)
            ->getJson('/api/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.status', 'Confirmed');
    }

    public function test_outlet_registration_rejects_arbitrary_user_ownership(): void
    {
        $user = User::factory()->outlet()->create();

        $this->postJson('/api/outlets', [
            'name' => 'Unsafe Outlet',
            'phone' => '081234567891',
            'address' => 'Jl. Unsafe',
            'city' => 'Jakarta',
            'district' => 'Menteng',
            'user_id' => $user->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('outlets', ['phone' => '081234567891']);
    }

    public function test_supplier_subscription_fields_have_explicit_defaults_and_allowed_values(): void
    {
        $supplier = Supplier::factory()->create();
        $this->assertSame('inactive', $supplier->subscription_status);
        $this->assertSame('basic', $supplier->subscription_plan);

        $supplier->update([
            'subscription_status' => 'active',
            'subscription_plan' => 'premium',
        ]);
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'subscription_status' => 'active',
            'subscription_plan' => 'premium',
        ]);
    }
}
