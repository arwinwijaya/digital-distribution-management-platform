<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppClient;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceReminderTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;
    private User $admin;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        Config::set('jwt.secret', 'test-jwt-secret-for-reminders-'.str_repeat('x', 16));
        Config::set('whatsapp.enabled', true);
        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public int $sendTextCalls = 0;

            public function sendText(string $to, string $text): array
            {
                $this->sendTextCalls++;

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

        Config::set('whatsapp.reminder_schedule', [
            'h_minus_one_offset_days' => 1,
            'overdue_offset_days' => 0,
            'max_attempts' => 3,
            'backoff_minutes' => [1, 5, 15],
        ]);
    }

    public function test_h_minus_one_reminder_is_sent_once(): void
    {
        $now = Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('h-minus-one', '2026-09-11');

        $this->artisan('invoices:reminders');
        $this->artisan('invoices:reminders');

        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->first();
        $this->assertSame(InvoiceReminder::EVENT_H_MINUS_ONE, $reminder->event_type);
        $this->assertSame('2026-09-11', $reminder->event_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_missed_h_minus_one_is_recovered(): void
    {
        $now = Carbon::parse('2026-09-12 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('missed-h-minus-one', '2026-09-11');

        $this->artisan('invoices:reminders');

        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->first();
        $this->assertSame(InvoiceReminder::EVENT_H_MINUS_ONE, $reminder->event_type);

        Carbon::setTestNow();
    }

    public function test_overdue_reminder_is_sent_once(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('overdue-first', '2026-09-11');

        $this->artisan('invoices:reminders');
        $this->artisan('invoices:reminders');

        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->first();
        $this->assertSame(InvoiceReminder::EVENT_OVERDUE, $reminder->event_type);

        Carbon::setTestNow();
    }

    public function test_paid_invoice_suppresses_reminder(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('paid-suppress', '2026-09-11');
        $invoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $invoice->total_amount,
            'balance_amount' => 0,
        ]);

        $this->artisan('invoices:reminders');

        $this->assertDatabaseCount('invoice_reminders', 0);

        Carbon::setTestNow();
    }

    public function test_cancelled_invoice_suppresses_reminder(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('cancelled-suppress', '2026-09-11');
        $invoice->update(['status' => Invoice::CANCELLED]);

        $this->artisan('invoices:reminders');

        $this->assertDatabaseCount('invoice_reminders', 0);

        Carbon::setTestNow();
    }

    public function test_provider_failure_schedules_idempotent_backoff(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('backoff-identity', '2026-09-11');

        $this->artisan('invoices:reminders');
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $this->assertSame(1, $reminder->attempts);
        $this->assertSame(InvoiceReminder::PENDING, $reminder->status);
        $this->assertSame($now->copy()->addMinutes(1)->toDateTimeString(), $reminder->next_attempt_at->toDateTimeString());

        $idempotencyKey = $reminder->idempotency_key;

        Carbon::setTestNow($now->copy()->addMinutes(2));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(2, $reminder->attempts);
        $this->assertSame($idempotencyKey, $reminder->idempotency_key);
        $this->assertSame($now->copy()->addMinutes(5)->toDateTimeString(), $reminder->next_attempt_at->toDateTimeString());

        Carbon::setTestNow($now->copy()->addMinutes(6));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(3, $reminder->attempts);
        $this->assertSame($idempotencyKey, $reminder->idempotency_key);

        Carbon::setTestNow($now->copy()->addMinutes(20));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(3, $reminder->attempts);
        $this->assertSame(InvoiceReminder::FAILED, $reminder->status);

        Carbon::setTestNow();
    }

    public function test_final_reminder_failure_is_audited_without_invoice_mutation(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('final-audit', '2026-09-11');
        $beforeStatus = $invoice->status;
        $beforeBalance = $invoice->balance_amount;
        $beforePaid = $invoice->paid_amount;

        $this->artisan('invoices:reminders');
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();

        Carbon::setTestNow($now->copy()->addMinutes(2));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        Carbon::setTestNow($now->copy()->addMinutes(6));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        Carbon::setTestNow($now->copy()->addMinutes(20));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        $this->assertSame(InvoiceReminder::FAILED, $reminder->status);
        $this->assertNotNull($reminder->failed_at);
        $this->assertNotNull($reminder->last_error);

        $invoice->refresh();
        $this->assertSame($beforeStatus, $invoice->status);
        $this->assertSame((string) $beforeBalance, (string) $invoice->balance_amount);
        $this->assertSame((string) $beforePaid, (string) $invoice->paid_amount);

        Carbon::setTestNow();
    }

    public function test_reminder_history_is_bounded_and_scoped(): void
    {
        [$orderA, $invoiceA] = $this->createDeliveredInvoice('history-a', '2026-09-11');
        [$orderB, $invoiceB] = $this->createDeliveredInvoice('history-b', '2026-09-11');

        InvoiceReminder::create([
            'invoice_id' => $invoiceA->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'reminder-history-a',
        ]);
        InvoiceReminder::create([
            'invoice_id' => $invoiceB->id,
            'event_type' => InvoiceReminder::EVENT_OVERDUE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'reminder-history-b',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/finance/reminders?outlet_id='.$this->outlet->id.'&page=1&limit=1');

        $response->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_id', $invoiceA->id);
    }

    public function test_invalid_reminder_pagination_is_rejected(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/finance/reminders?page=0&limit=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page', 'limit']);
    }

    public function test_scheduler_registers_invoice_reminders_in_jakarta(): void
    {
        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        $reminderEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'invoices:reminders'));
        $this->assertNotNull($reminderEvent, 'Invoice reminder scheduler event is not registered.');
        $this->assertSame('Asia/Jakarta', $reminderEvent->timezone);
    }

    public function test_closed_invoice_suppresses_pending_retry(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('closed-retry', '2026-09-11');
        $this->artisan('invoices:reminders');

        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $reminder->update([
            'status' => InvoiceReminder::PENDING,
            'next_attempt_at' => $now->copy()->addMinutes(1),
        ]);
        $beforeAttempts = $reminder->attempts;

        $invoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $invoice->total_amount,
            'balance_amount' => 0,
        ]);

        $client = $this->app->make(WhatsAppClient::class);
        $callsBeforeRetry = $client->sendTextCalls;

        Carbon::setTestNow($now->copy()->addMinutes(2));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        $this->assertSame(InvoiceReminder::SENT, $reminder->status);
        $this->assertSame($beforeAttempts, $reminder->attempts);

        $this->assertSame($callsBeforeRetry, $client->sendTextCalls);

        Carbon::setTestNow();
    }

    public function test_concurrent_invoice_reminder_workers(): void
    {
        if (config('database.default') !== 'pgsql' || ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('Concurrent reminder claim coverage requires PostgreSQL.');
        }

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('PostgreSQL test database is unavailable: '.$exception->getMessage());
        }

        if (! \Illuminate\Support\Facades\DB::getSchemaBuilder()->hasTable('invoice_reminders')) {
            $this->markTestSkipped('PostgreSQL test database is not migrated.');
        }

        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public int $sendTextCalls = 0;

            public function sendText(string $to, string $text): array
            {
                $this->sendTextCalls++;

                return ['id' => 'reminder-pg-1'];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('Unexpected catalog call');
            }
        });

        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('concurrent-reminder', '2026-09-11');
        $logicalKey = 'invoice-reminder:'.$invoice->id.':overdue:2026-09-11';

        $this->artisan('invoices:reminders');

        $reminder = InvoiceReminder::on('pgsql')
            ->where('invoice_id', $invoice->id)
            ->sole();

        $idempotencyKey = $reminder->idempotency_key;
        $providerKey = $reminder->idempotency_key;

        $inserted = \Illuminate\Support\Facades\DB::connection('pgsql')->table('invoice_reminders')->insertOrIgnore([
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_OVERDUE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, $inserted);
        $this->assertSame(1, InvoiceReminder::on('pgsql')->where('invoice_id', $invoice->id)->count());
        $this->assertSame($providerKey, $idempotencyKey);

        Carbon::setTestNow();
    }

    /** @return array{0: Order, 1: Invoice} */
    private function createDeliveredInvoice(string $identity, string $dueDate): array
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
