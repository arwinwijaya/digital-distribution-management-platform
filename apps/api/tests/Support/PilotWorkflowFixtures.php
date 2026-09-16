<?php

namespace Tests\Support;

use App\Contracts\WhatsAppClient;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\OperationalEvent;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

trait PilotWorkflowFixtures
{
    protected User $pilotOutletUser;
    protected User $pilotAdmin;
    protected User $pilotDriver;
    protected Outlet $pilotOutlet;
    protected string $pilotOutletToken;
    protected string $pilotAdminToken;
    protected string $pilotDriverToken;
    protected array $pilotProducts = [];

    protected function initializePilotWorkflowFixtures(): void
    {
        config(['pre_pilot.enabled' => true, 'pre_pilot.kill_switch' => false]);
        $this->bindWhatsAppClient();

        $this->pilotOutletUser = User::factory()->outlet()->create([
            'email' => 'pilot-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->pilotOutlet = Outlet::factory()->create([
            'user_id' => $this->pilotOutletUser->id,
            'phone' => '+6281234567890',
            'is_active' => true,
        ]);
        $this->pilotAdmin = User::factory()->admin()->create([
            'email' => 'pilot-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->pilotDriver = User::factory()->driver()->create([
            'email' => 'pilot-driver@example.test',
            'password' => Hash::make('password123'),
        ]);

        $this->pilotOutletToken = $this->pilotLogin($this->pilotOutletUser);
        $this->pilotAdminToken = $this->pilotLogin($this->pilotAdmin);
        $this->pilotDriverToken = $this->pilotLogin($this->pilotDriver);

        for ($i = 0; $i < 3; $i++) {
            $this->pilotProducts[] = Product::factory()->create([
                'price' => (10000 + $i * 5000),
                'stock_quantity' => 500,
                'is_active' => true,
            ]);
        }
    }

    protected function pilotLogin(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    protected function createPilotOrder(string $idempotencyKey, int $productIndex = 0): Order
    {
        $response = $this->withToken($this->pilotOutletToken)->postJson('/api/orders', [
            'items' => [[
                'product_id' => $this->pilotProducts[$productIndex]->id,
                'quantity' => 10,
            ]],
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    protected function approvePilotOrder(Order $order): TestResponse
    {
        return $this->withToken($this->pilotAdminToken)->putJson("/api/orders/{$order->id}/approve");
    }

    protected function startPilotDelivery(Order $order): Delivery
    {
        $response = $this->withToken($this->pilotAdminToken)->postJson('/api/deliveries', [
            'order_id' => $order->id,
            'driver_id' => $this->pilotDriver->id,
        ]);

        $response->assertCreated();

        return Delivery::findOrFail($response->json('data.id'));
    }

    protected function completePilotDelivery(Delivery $delivery): TestResponse
    {
        $this->withToken($this->pilotDriverToken)->patchJson("/api/deliveries/{$delivery->id}/status", [
            'status' => 'in_progress',
        ])->assertOk();

        return $this->withToken($this->pilotDriverToken)->patchJson("/api/deliveries/{$delivery->id}/status", [
            'status' => 'delivered',
            'recipient_name' => 'Outlet Manager',
            'proof_of_delivery_url' => 'https://example.com/proof.jpg',
        ]);
    }

    protected function recordPilotPayment(Order $order, int $amount, string $idempotencyKey): TestResponse
    {
        return $this->withToken($this->pilotAdminToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    protected function cancelPilotOrder(Order $order): TestResponse
    {
        return $this->withToken($this->pilotAdminToken)->putJson("/api/orders/{$order->id}/cancel");
    }

    protected function bindWhatsAppClient(): void
    {
        $client = new class implements WhatsAppClient
        {
            public function sendText(string $to, string $text): array
            {
                return ['id' => 'pilot-sent-'.uniqid()];
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                return ['id' => 'pilot-keyed-'.uniqid()];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                return ['id' => 'pilot-catalog'];
            }
        };

        $this->app->instance(WhatsAppClient::class, $client);
    }
}
