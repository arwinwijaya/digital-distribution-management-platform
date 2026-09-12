<?php

namespace Tests\Support;

use App\Contracts\WhatsAppClient;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

trait OperationalReadinessFixtures
{
    protected User $admin;

    protected User $outletUser;

    protected User $financeUser;

    protected User $driver;

    protected Outlet $outlet;

    protected string $adminToken;

    protected string $outletToken;

    protected string $driverToken;

    protected object $whatsAppClient;

    protected function initializeOperationalReadinessFixtures(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        config(['whatsapp.enabled' => true]);
        $this->bindSuccessfulWhatsAppClient();

        $this->admin = $this->operationalUser('admin', 't10-admin@example.test');
        $this->outletUser = $this->operationalUser('outlet', 't10-outlet@example.test');
        $this->financeUser = $this->operationalUser('sales', 't10-finance@example.test');
        $this->driver = $this->operationalUser('driver', 't10-driver@example.test');
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'phone' => '+628123456789',
            'is_active' => true,
        ]);

        $this->adminToken = $this->login($this->admin);
        $this->outletToken = $this->login($this->outletUser);
        $this->driverToken = $this->login($this->driver);
    }

    protected function resetOperationalReadinessClock(): void
    {
        Carbon::setTestNow();
    }

    private function bindSuccessfulWhatsAppClient(): void
    {
        $this->whatsAppClient = new class implements WhatsAppClient
        {
            /** @var array<int, string> */
            public array $idempotencyKeys = [];

            public function sendText(string $to, string $text): array
            {
                return ['id' => 't10-text-'.count($this->idempotencyKeys)];
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                $this->idempotencyKeys[] = $idempotencyKey;

                return ['id' => 't10-keyed-'.count($this->idempotencyKeys)];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                return ['id' => 't10-catalog'];
            }
        };
        $this->app->instance(WhatsAppClient::class, $this->whatsAppClient);
    }

    protected function operationalUser(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('status', 'success')->json('data.token');
    }

    protected function assignFinanceAndLogin(): string
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/'.$this->financeUser->id.'/finance-role')
            ->assertOk()->assertJsonPath('data.role', 'finance');

        return $this->login($this->financeUser->fresh());
    }

    protected function setPaymentTerm(int $days): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/admin/outlets/'.$this->outlet->id.'/payment-terms', ['payment_term_days' => $days])
            ->assertOk()->assertJsonPath('data.payment_term_days', $days);
    }

    protected function createOrderThroughHttp(string $key, int $total): Order
    {
        $product = Product::factory()->create(['price' => $total, 'stock_quantity' => 20, 'is_active' => true]);
        $response = $this->withToken($this->outletToken)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 't10-'.$key,
        ])->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'New')
            ->assertJsonPath('data.total_amount', number_format($total, 2, '.', ''));

        return Order::findOrFail($response->json('data.id'));
    }

    /** @return array{0: Order, 1: Invoice} */
    protected function createApprovedOrder(string $key, int $total = 1000000): array
    {
        $order = $this->createOrderThroughHttp($key, $total);
        $response = $this->approve($order)->assertOk()->assertJsonPath('data.status', 'Confirmed');
        $invoice = Invoice::findOrFail($response->json('data.invoice.id'));

        return [$order->fresh(), $invoice];
    }

    protected function approve(Order $order): TestResponse
    {
        return $this->withToken($this->adminToken)->putJson('/api/orders/'.$order->id.'/approve');
    }

    protected function startDelivery(Order $order): Delivery
    {
        $assigned = $this->withToken($this->adminToken)->postJson('/api/deliveries', [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
        ])->assertCreated()->assertJsonPath('data.status', Delivery::ASSIGNED);
        $delivery = Delivery::findOrFail($assigned->json('data.id'));
        $this->withToken($this->driverToken)
            ->patchJson('/api/deliveries/'.$delivery->id.'/status', ['status' => Delivery::IN_PROGRESS])
            ->assertOk()->assertJsonPath('data.status', Delivery::IN_PROGRESS);

        return $delivery->fresh();
    }

    protected function deliver(Delivery $delivery): TestResponse
    {
        return $this->withToken($this->driverToken)->patchJson('/api/deliveries/'.$delivery->id.'/status', [
            'status' => Delivery::DELIVERED,
            'recipient_name' => 'T10 recipient',
            'proof_of_delivery_url' => 'https://example.test/t10-proof.jpg',
        ]);
    }

    protected function postPayment(string $token, Order $order, int $amount, string $key): TestResponse
    {
        return $this->withToken($token)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'idempotency_key' => $key,
        ]);
    }

    protected function useFailingWhatsAppClient(): object
    {
        $client = new class implements WhatsAppClient
        {
            /** @var array<int, string> */
            public array $idempotencyKeys = [];

            public function sendText(string $to, string $text): array
            {
                throw new \RuntimeException('T10 provider unavailable');
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                $this->idempotencyKeys[] = $idempotencyKey;
                throw new \RuntimeException('T10 provider unavailable');
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('T10 provider unavailable');
            }
        };
        $this->app->instance(WhatsAppClient::class, $client);

        return $client;
    }
}
