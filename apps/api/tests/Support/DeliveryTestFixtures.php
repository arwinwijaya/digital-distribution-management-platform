<?php

namespace Tests\Support;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait DeliveryTestFixtures
{
    /**
     * @return array{admin: User, driver: User, outlet: Outlet, order: Order, delivery: Delivery}
     */
    protected function createDeliveryFixture(
        string $orderReference,
        string $idempotencyKey,
        string $status = Delivery::IN_PROGRESS,
        bool $assignThroughApi = false,
    ): array {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-'.$idempotencyKey.'@example.com',
            'password' => Hash::make('password'),
        ]);
        $driver = User::factory()->driver()->create([
            'email' => 'driver-'.$idempotencyKey.'@example.com',
            'password' => Hash::make('password'),
        ]);
        $outlet = Outlet::factory()->create();
        $order = Order::create([
            'order_id' => $orderReference,
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => $idempotencyKey,
        ]);

        if ($assignThroughApi) {
            $response = $this->withHeader('Authorization', 'Bearer '.$this->loginAsDeliveryUser($admin))
                ->postJson('/api/deliveries', [
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                ]);
            $response->assertCreated();
            $delivery = Delivery::findOrFail($response->json('data.id'));
        } else {
            $delivery = Delivery::create([
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'assigned_by_id' => $admin->id,
                'status' => $status,
                'assigned_at' => now()->subMinute(),
                'started_at' => $status === Delivery::IN_PROGRESS ? now() : null,
            ]);
        }

        return compact('admin', 'driver', 'outlet', 'order', 'delivery');
    }

    protected function loginAsDeliveryUser(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }
}
