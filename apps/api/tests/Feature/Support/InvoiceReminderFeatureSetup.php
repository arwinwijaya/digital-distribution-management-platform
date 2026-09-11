<?php

namespace Tests\Feature\Support;

use App\Contracts\WhatsAppClient;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

trait InvoiceReminderFeatureSetup
{
    protected function setUpInvoiceReminderFeature(): void
    {
        Config::set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        Config::set('jwt.secret', 'test-jwt-secret-for-reminders-'.str_repeat('x', 16));
        Config::set('whatsapp.enabled', true);
        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public int $sendTextCalls = 0;
            public array $idempotencyKeys = [];

            public function sendText(string $to, string $text): array
            {
                $this->sendTextCalls++;

                throw new \RuntimeException('unkeyed provider path must not be used');
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                $this->idempotencyKeys[] = $idempotencyKey;

                throw new \RuntimeException('provider unavailable');
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('provider unavailable');
            }
        });

        $this->admin = User::factory()->admin()->create([
            'email' => 'reminder-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'phone' => '+628123456789',
            'is_active' => true,
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        Config::set('whatsapp.reminder_schedule.backoff_minutes', [1, 5, 15]);
    }

    protected function useSuccessfulReminderClient(): object
    {
        $client = new class implements WhatsAppClient
        {
            public array $idempotencyKeys = [];

            public function sendText(string $to, string $text): array
            {
                throw new \LogicException('Reminder delivery must use the keyed provider method.');
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                $this->idempotencyKeys[] = $idempotencyKey;

                return ['id' => 'provider-'.count($this->idempotencyKeys)];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('Unexpected catalog call');
            }
        };
        $this->app->instance(WhatsAppClient::class, $client);

        return $client;
    }

    /** @return array{0: Order, 1: Invoice} */
    protected function createDeliveredInvoice(string $identity, string $dueDate): array
    {
        $order = Order::create([
            'order_id' => 'ORD-REMINDER-'.$identity,
            'outlet_id' => $this->outlet->id,
            'status' => 'Delivered',
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'order-reminder-'.$identity,
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-REMINDER-'.$identity,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'balance_amount' => 1000000,
            'status' => Invoice::UNPAID,
        ]);

        return [$order, $invoice];
    }
}
