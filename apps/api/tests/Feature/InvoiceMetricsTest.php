<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $finance;
    protected Outlet $outlet;
    protected string $adminToken;
    protected string $financeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'metrics-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->finance = User::factory()->create([
            'email' => 'metrics-finance@example.test',
            'password' => Hash::make('password123'),
            'role' => 'finance',
        ]);
        $this->outlet = Outlet::factory()->create(['is_active' => true]);
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->adminToken = $this->login($this->admin);
        $this->financeToken = $this->login($this->finance);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_metrics_use_default_window_and_current_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->invoice('inside', '2026-09-10', '2026-09-25', 100, 0, Invoice::UNPAID);
        [, $outside] = $this->invoice('outside', '2026-08-01', '2026-08-20', 200, 0, Invoice::UNPAID);
        $this->invoice('paid', '2026-09-12', '2026-09-18', 300, 300, Invoice::PAID);
        $outside->update(['status' => Invoice::PARTIALLY_PAID, 'paid_amount' => 50, 'balance_amount' => 150]);

        $response = $this->withToken($this->financeToken)->getJson('/api/finance/metrics');

        $response->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 2)
            ->assertJsonPath('data.outstanding_balance.amount', 250)
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 1)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.paid', 1)
            ->assertJsonPath('data.window.start_date', '2026-08-22')
            ->assertJsonPath('data.window.end_date', '2026-09-20');
    }

    public function test_metrics_return_issued_invoices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->invoice('inside', '2026-09-01', '2026-09-08');
        $this->invoice('outside', '2026-08-01', '2026-08-08');

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()->assertJsonPath('data.issued_invoices.count', 1);
        $this->withToken($this->financeToken)->getJson('/api/finance/metrics?start_date=2026-08-01&end_date=2026-08-31')
            ->assertOk()->assertJsonPath('data.issued_invoices.count', 1);
    }

    public function test_metrics_return_current_outstanding_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->invoice('old-unpaid', '2026-07-01', '2026-07-08', 100, 0, Invoice::UNPAID);
        $this->invoice('partial', '2026-09-10', '2026-09-17', 200, 75, Invoice::PARTIALLY_PAID);
        $this->invoice('paid', '2026-09-10', '2026-09-17', 300, 300, Invoice::PAID);
        $this->invoice('cancelled', '2026-09-10', '2026-09-17', 400, 0, Invoice::CANCELLED);

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()->assertJsonPath('data.outstanding_balance.amount', 225);
    }

    public function test_metrics_return_overdue_rate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->invoice('overdue', '2026-09-01', '2026-09-19', 100, 0, Invoice::UNPAID);
        $this->invoice('not-overdue', '2026-09-01', '2026-09-21', 100, 0, Invoice::UNPAID);
        $this->invoice('paid-overdue', '2026-09-01', '2026-09-01', 100, 100, Invoice::PAID);
        $this->invoice('cancelled-overdue', '2026-09-01', '2026-09-01', 100, 0, Invoice::CANCELLED);

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.overdue_rate.rate', 50)
            ->assertJsonPath('data.overdue_rate.overdue_count', 1)
            ->assertJsonPath('data.overdue_rate.active_count', 2);
    }

    public function test_metrics_measure_full_collection_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        [$paidOrder, $paidInvoice] = $this->invoice('fully-paid', '2026-09-01', '2026-09-10', 100, 100, Invoice::PAID);
        $this->payment($paidOrder, 40, '2026-09-05 10:00:00');
        $this->payment($paidOrder, 60, '2026-09-10 10:00:00');
        [$partialOrder] = $this->invoice('partial-only', '2026-09-01', '2026-09-10', 100, 40, Invoice::PARTIALLY_PAID);
        $this->payment($partialOrder, 40, '2026-09-05 10:00:00');

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.collection_time.average_days', 9)
            ->assertJsonPath('data.collection_time.fully_collected_count', 1);
    }

    public function test_metrics_return_payment_status_breakdown(): void
    {
        foreach ([Invoice::UNPAID, Invoice::PARTIALLY_PAID, Invoice::PAID, Invoice::CANCELLED] as $index => $status) {
            $this->invoice('status-'.$index, '2026-09-10', '2026-09-20', 100, $status === Invoice::PAID ? 100 : 0, $status);
        }

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 1)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.cancelled', 1);
    }

    public function test_metrics_return_reminder_success_and_failure(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        [, $inside] = $this->invoice('reminder-inside', '2026-09-10', '2026-09-20');
        [, $outside] = $this->invoice('reminder-outside', '2026-08-01', '2026-08-10');
        InvoiceReminder::factory()->sent()->create(['invoice_id' => $inside->id, 'event_date' => '2026-09-15']);
        InvoiceReminder::factory()->failed()->create(['invoice_id' => $inside->id, 'event_date' => '2026-09-16']);
        InvoiceReminder::factory()->sent()->create(['invoice_id' => $outside->id, 'event_date' => '2026-08-05']);

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.reminders.success', 1)
            ->assertJsonPath('data.reminders.failure', 1);
    }

    public function test_metrics_accept_valid_custom_window(): void
    {
        $this->invoice('selected', '2026-09-03', '2026-09-10');
        $this->invoice('not-selected', '2026-09-10', '2026-09-17');

        $this->withToken($this->financeToken)->getJson('/api/finance/metrics?start_date=2026-09-01&end_date=2026-09-05')
            ->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 1)
            ->assertJsonPath('data.window.start_date', '2026-09-01')
            ->assertJsonPath('data.window.end_date', '2026-09-05');
    }

    public function test_metrics_reject_invalid_custom_window(): void
    {
        foreach ([
            '?start_date=not-a-date&end_date=2026-09-05',
            '?start_date=2026-09-06&end_date=2026-09-05',
            '?start_date=2025-01-01&end_date=2026-01-02',
        ] as $query) {
            $this->withToken($this->financeToken)->getJson('/api/finance/metrics'.$query)->assertStatus(422);
        }
    }

    public function test_metrics_are_zero_safe(): void
    {
        $response = $this->withToken($this->financeToken)->getJson('/api/finance/metrics');

        $response->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 0)
            ->assertJsonPath('data.outstanding_balance.amount', 0)
            ->assertJsonPath('data.overdue_rate.rate', 0)
            ->assertJsonPath('data.collection_time.average_days', 0)
            ->assertJsonPath('data.reminders.success', 0)
            ->assertJsonPath('data.reminders.failure', 0);
        $data = $response->json('data.payment_status_breakdown');
        $this->assertSame(['unpaid' => 0, 'partially_paid' => 0, 'paid' => 0, 'cancelled' => 0], $data);
    }

    public function test_metrics_are_authorized_and_outlet_scoped(): void
    {
        $this->invoice('own', '2026-09-10', '2026-09-20', 100, 0, Invoice::UNPAID, $this->outlet);
        $otherOutlet = Outlet::factory()->create(['is_active' => true]);
        $this->invoice('other', '2026-09-10', '2026-09-20', 900, 0, Invoice::UNPAID, $otherOutlet);

        $this->withToken($this->adminToken)->getJson('/api/finance/metrics')->assertOk()
            ->assertJsonPath('data.outstanding_balance.amount', 1000);
        $this->withToken($this->financeToken)->getJson('/api/finance/metrics')->assertOk()
            ->assertJsonPath('data.outstanding_balance.amount', 1000);

        $outletUser = User::factory()->outlet()->create([
            'email' => 'metrics-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet->update(['user_id' => $outletUser->id]);
        $outletToken = $this->login($outletUser);
        $this->withToken($outletToken)->getJson('/api/finance/metrics')->assertOk()
            ->assertJsonPath('data.outstanding_balance.amount', 100);
        $this->withToken('invalid-token')->getJson('/api/finance/metrics')->assertUnauthorized();
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    /** @return array{0: Order, 1: Invoice} */
    protected function invoice(string $key, string $issueDate, string $dueDate, int $total = 100, int $paid = 0, string $status = Invoice::UNPAID, ?Outlet $outlet = null): array
    {
        $outlet ??= $this->outlet;
        $order = Order::create([
            'order_id' => 'ORD-METRICS-'.$key.'-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => $status === Invoice::PAID ? 'Paid' : 'Delivered',
            'total_amount' => $total,
            'paid_amount' => $paid,
            'commission_percentage' => 2,
            'idempotency_key' => 'metrics-order-'.$key.'-'.uniqid(),
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $outlet->id,
            'invoice_number' => 'INV-'.$key.'-'.uniqid(),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_amount' => max(0, $total - $paid),
            'status' => $status,
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update([
            'created_at' => Carbon::parse($issueDate, 'Asia/Jakarta'),
            'updated_at' => Carbon::parse($issueDate, 'Asia/Jakarta'),
        ]);

        return [$order, $invoice->fresh()];
    }

    protected function payment(Order $order, int $amount, string $createdAt): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => 'completed',
            'idempotency_key' => 'metrics-payment-'.uniqid(),
        ]);
        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => Carbon::parse($createdAt, 'Asia/Jakarta'),
            'updated_at' => Carbon::parse($createdAt, 'Asia/Jakarta'),
        ]);

        return $payment->fresh();
    }
}
