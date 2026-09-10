<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    use DeliveryTestFixtures;

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
        $token = $this->loginAsDeliveryUser($admin);

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

    public function test_delivered_requires_nonblank_recipient(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-PROOF-RECIPIENT',
            'delivery-proof-recipient',
        );
        $delivery = $fixture['delivery'];
        $token = $this->loginAsDeliveryUser($fixture['driver']);

        foreach ([
            ['status' => Delivery::DELIVERED, 'proof_of_delivery_url' => 'https://example.com/proof.jpg'],
            ['status' => Delivery::DELIVERED, 'recipient_name' => '   ', 'proof_of_delivery_url' => 'https://example.com/proof.jpg'],
        ] as $payload) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->patchJson('/api/deliveries/'.$delivery->id.'/status', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['recipient_name']);

            $this->assertDatabaseHas('deliveries', [
                'id' => $delivery->id,
                'status' => Delivery::IN_PROGRESS,
            ]);
        }
    }

    public function test_delivered_requires_valid_proof_url(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-PROOF-URL',
            'delivery-proof-url',
        );
        $delivery = $fixture['delivery'];
        $token = $this->loginAsDeliveryUser($fixture['driver']);

        foreach ([
            ['status' => Delivery::DELIVERED, 'recipient_name' => 'Outlet manager'],
            ['status' => Delivery::DELIVERED, 'recipient_name' => 'Outlet manager', 'proof_of_delivery_url' => 'not-a-url'],
        ] as $payload) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->patchJson('/api/deliveries/'.$delivery->id.'/status', $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['proof_of_delivery_url']);

            $this->assertDatabaseHas('deliveries', [
                'id' => $delivery->id,
                'status' => Delivery::IN_PROGRESS,
            ]);
        }
    }

    public function test_delivery_completion_persists_proof(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-PROOF-PERSISTED',
            'delivery-proof-persisted',
        );
        $delivery = $fixture['delivery'];
        $token = $this->loginAsDeliveryUser($fixture['driver']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/deliveries/'.$delivery->id.'/status', [
                'status' => Delivery::DELIVERED,
                'recipient_name' => 'Outlet manager',
                'proof_of_delivery_url' => 'https://example.com/persisted-proof.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Delivery::DELIVERED)
            ->assertJsonPath('data.recipient_name', 'Outlet manager')
            ->assertJsonPath('data.proof_of_delivery_url', 'https://example.com/persisted-proof.jpg');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => Delivery::DELIVERED,
            'recipient_name' => 'Outlet manager',
            'proof_of_delivery_url' => 'https://example.com/persisted-proof.jpg',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'Delivered']);
    }

    public function test_non_delivery_transitions_do_not_require_proof(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-NON-COMPLETION',
            'delivery-non-completion',
            Delivery::ASSIGNED,
        );
        $delivery = $fixture['delivery'];
        $token = $this->loginAsDeliveryUser($fixture['driver']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson('/api/deliveries/'.$delivery->id.'/status', ['status' => Delivery::IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.status', Delivery::IN_PROGRESS);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => Delivery::IN_PROGRESS,
            'recipient_name' => null,
            'proof_of_delivery_url' => null,
        ]);
    }

}
