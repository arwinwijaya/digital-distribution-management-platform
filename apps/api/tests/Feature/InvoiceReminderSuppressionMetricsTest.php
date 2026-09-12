<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceReminderSuppressionMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $finance;
    protected string $financeToken;
    protected int $providerCallCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        config(['jwt.secret' => 'test-jwt-secret-for-suppression-'.str_repeat('x', 16)]);
        config(['whatsapp.enabled' => true]);

        $this->outlet = Outlet::factory()->create(['is_active' => true]);
        $this->finance = User::factory()->create([
            'email' => 'reminder-suppression-finance@example.test',
            'password' => Hash::make('password123'),
            'role' => 'finance',
        ]);
        $this->financeToken = $this->postJson('/api/auth/login', [
            'email' => $this->finance->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_closed_before_retry_suppression_does_not_increment_success_or_failure(): void
    {
        $now = Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        $this->useCountingReminderClient();

        [$suppressedOrder, $suppressedInvoice] = $this->createDeliveredInvoice('suppressed', '2026-09-11');
        $suppressedReminder = InvoiceReminder::create([
            'invoice_id' => $suppressedInvoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::PENDING,
            'attempts' => 0,
            'next_attempt_at' => $now->copy()->addMinutes(1),
            'idempotency_key' => 'suppressed-regression-suppressed',
        ]);

        [$canonicalOrder, $canonicalInvoice] = $this->createDeliveredInvoice('canonical', '2026-09-11');
        [$failedOrder, $failedInvoice] = $this->createDeliveredInvoice('failed', '2026-09-11');
        InvoiceReminder::create([
            'invoice_id' => $canonicalInvoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-15',
            'status' => InvoiceReminder::SENT,
            'sent_at' => Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta'),
            'idempotency_key' => 'canonical-success',
        ]);
        InvoiceReminder::create([
            'invoice_id' => $failedInvoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-16',
            'status' => InvoiceReminder::FAILED,
            'failed_at' => Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta'),
            'last_error' => 'seeded failure',
            'idempotency_key' => 'canonical-failed',
        ]);

        $beforeProviderCalls = $this->providerCallCount;

        $suppressedInvoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $suppressedInvoice->total_amount,
            'balance_amount' => 0,
        ]);

        Carbon::setTestNow($now->copy()->addMinutes(5));
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();

        $suppressedReminder->refresh();
        $this->assertSame(InvoiceReminder::SUPPRESSED, $suppressedReminder->status);
        $this->assertSame(0, $suppressedReminder->attempts);
        $this->assertNull($suppressedReminder->sent_at);
        $this->assertNull($suppressedReminder->failed_at);
        $this->assertSame('Suppressed: invoice closed before retry.', $suppressedReminder->last_error);
        $this->assertSame($suppressedOrder->outlet_id, $suppressedInvoice->outlet_id);

        $this->assertSame($beforeProviderCalls, $this->providerCallCount);

        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->financeToken = $this->postJson('/api/auth/login', [
            'email' => $this->finance->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');

        $this->withToken($this->financeToken)
            ->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.reminders.success', 1)
            ->assertJsonPath('data.reminders.failure', 1);

        $reminderHistory = $this->withToken($this->financeToken)
            ->getJson('/api/finance/reminders?page=1&limit=100')
            ->assertOk()
            ->json('data');

        $suppressedRow = collect($reminderHistory)->firstWhere('id', $suppressedReminder->id);
        $this->assertNotNull($suppressedRow);
        $this->assertSame(InvoiceReminder::SUPPRESSED, $suppressedRow['status']);
        $this->assertNull($suppressedRow['sent_at']);
        $this->assertNull($suppressedRow['failed_at']);
        $this->assertSame($suppressedInvoice->id, $suppressedRow['invoice_id']);

        $invoiceRecord = Invoice::where('id', $suppressedInvoice->id)->sole();
        $this->assertSame(Invoice::PAID, $invoiceRecord->status);
        $this->assertSame('1000000.00', (string) $invoiceRecord->paid_amount);
        $this->assertSame('0.00', (string) $invoiceRecord->balance_amount);
    }

    private function useCountingReminderClient(): void
    {
        $test = $this;
        $this->app->instance(\App\Contracts\WhatsAppClient::class, new class ($test) implements \App\Contracts\WhatsAppClient
        {
            public function __construct(private readonly object $test) {}

            public function sendText(string $to, string $text): array
            {
                throw new \LogicException('Reminder delivery must use the keyed provider method.');
            }

            public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
            {
                $this->test->providerCallCount++;

                return ['id' => 'msg-'.$this->test->providerCallCount];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('Unexpected catalog call');
            }
        });
    }

    /** @return array{0: Order, 1: Invoice} */
    private function createDeliveredInvoice(string $identity, string $dueDate): array
    {
        $order = Order::create([
            'order_id' => 'ORD-SUPPRESS-'.$identity.'-'.uniqid(),
            'outlet_id' => $this->outlet->id,
            'status' => 'Delivered',
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'order-suppress-'.$identity.'-'.uniqid(),
        ]);

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-SUPPRESS-'.$identity.'-'.uniqid(),
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
