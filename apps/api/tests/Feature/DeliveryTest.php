<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_confirmed_order_to_a_driver(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-delivery@example.com',
            'password' => Hash::make('password123'),
        ]);
        $driver = User::factory()->driver()->create();
        $outlet = Outlet::factory()->create();
        $order = Order::create([
            'order_id' => 'ORD-DELIVERY-001',
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'delivery-test-001',
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/deliveries', [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.status', 'assigned');
    }

    public function test_unconfirmed_orders_and_non_drivers_cannot_be_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $driver = User::factory()->driver()->create();
        $outlet = Outlet::factory()->create();
        $order = Order::create([
            'order_id' => 'ORD-DELIVERY-NEW',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 100000,
            'idempotency_key' => 'delivery-test-new',
        ]);
        $token = $this->loginAs($admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/deliveries', ['order_id' => $order->id, 'driver_id' => $driver->id])
            ->assertStatus(422);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/deliveries', ['order_id' => 99999, 'driver_id' => $driver->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/deliveries', ['order_id' => $order->id, 'driver_id' => $admin->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['driver_id']);
    }

    public function test_only_assigned_driver_can_transition_and_history_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $driver = User::factory()->driver()->create();
        $otherDriver = User::factory()->driver()->create();
        $outlet = Outlet::factory()->create();
        $order = Order::create([
            'order_id' => 'ORD-DELIVERY-STATUS',
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'delivery-test-status',
        ]);
        $adminToken = $this->loginAs($admin);
        $delivery = $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->postJson('/api/deliveries', ['order_id' => $order->id, 'driver_id' => $driver->id])
            ->json('data');

        $this->withHeader('Authorization', 'Bearer '.$this->loginAs($otherDriver))
            ->patchJson('/api/deliveries/'.$delivery['id'].'/status', ['status' => 'in_progress'])
            ->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$this->loginAs($driver))
            ->patchJson('/api/deliveries/'.$delivery['id'].'/status', ['status' => 'delivered'])
            ->assertStatus(422);
        $this->withHeader('Authorization', 'Bearer '.$this->loginAs($driver))
            ->patchJson('/api/deliveries/'.$delivery['id'].'/status', ['status' => 'in_progress'])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$this->loginAs($driver))
            ->patchJson('/api/deliveries/'.$delivery['id'].'/status', [
                'status' => 'delivered',
                'recipient_name' => 'Outlet manager',
                'proof_of_delivery' => ['photo_url' => 'https://example.com/proof.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.proof_of_delivery.photo_url', 'https://example.com/proof.jpg')
            ->assertJsonCount(3, 'data.status_history');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Delivered']);
        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->getJson('/api/deliveries/'.$delivery['id'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonCount(3, 'data.status_history');
    }

    private function loginAs(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }
}
