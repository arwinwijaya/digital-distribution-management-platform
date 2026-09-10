<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryTestFixtures;
use Tests\TestCase;

class DeliveryAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use DeliveryTestFixtures;

    public function test_only_assigned_driver_can_transition_delivery(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-AUTHORIZATION',
            'delivery-authorization',
            Delivery::ASSIGNED,
            true,
        );
        $otherDriver = User::factory()->driver()->create();

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($otherDriver))
            ->patchJson('/api/deliveries/'.$fixture['delivery']->id.'/status', ['status' => Delivery::IN_PROGRESS])
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($fixture['driver']))
            ->patchJson('/api/deliveries/'.$fixture['delivery']->id.'/status', ['status' => Delivery::IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.status', Delivery::IN_PROGRESS);

        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery']->id,
            'status' => Delivery::IN_PROGRESS,
        ]);
    }

    public function test_assigned_driver_cannot_complete_without_delivery_proof(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-AUTHORIZATION-PROOF',
            'delivery-authorization-proof',
            Delivery::ASSIGNED,
            true,
        );

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($fixture['driver']))
            ->patchJson('/api/deliveries/'.$fixture['delivery']->id.'/status', ['status' => Delivery::DELIVERED])
            ->assertStatus(422);

        $this->assertDatabaseHas('deliveries', [
            'id' => $fixture['delivery']->id,
            'status' => Delivery::ASSIGNED,
        ]);
    }

    public function test_delivery_completion_records_status_history_and_proof_response(): void
    {
        $fixture = $this->createDeliveryFixture(
            'ORD-DELIVERY-HISTORY',
            'delivery-history',
            Delivery::ASSIGNED,
            true,
        );
        $token = $this->loginAsDeliveryUser($fixture['driver']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/deliveries/'.$fixture['delivery']->id.'/status', ['status' => Delivery::IN_PROGRESS])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/deliveries/'.$fixture['delivery']->id.'/status', [
                'status' => Delivery::DELIVERED,
                'recipient_name' => 'Outlet manager',
                'proof_of_delivery_url' => 'https://example.com/proof.jpg',
                'proof_of_delivery' => ['photo_url' => 'https://example.com/proof.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', Delivery::DELIVERED)
            ->assertJsonPath('data.proof_of_delivery.photo_url', 'https://example.com/proof.jpg')
            ->assertJsonCount(3, 'data.status_history');

        $this->assertDatabaseHas('orders', ['id' => $fixture['order']->id, 'status' => 'Delivered']);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $fixture['order']->id,
            'status' => 'Delivered',
            'notes' => 'Delivery completed',
        ]);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $fixture['order']->id)->where('status', 'Delivered')->count());

        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($fixture['admin']))
            ->getJson('/api/deliveries/'.$fixture['delivery']->id)
            ->assertOk()
            ->assertJsonPath('data.status', Delivery::DELIVERED)
            ->assertJsonCount(3, 'data.status_history')
            ->assertJsonPath('data.status_history.2.status', Delivery::DELIVERED);
        $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($fixture['admin']))
            ->getJson('/api/orders/'.$fixture['order']->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'Delivered')
            ->assertJsonCount(1, 'data.status_history')
            ->assertJsonPath('data.status_history.0.status', 'Delivered');
    }
}
