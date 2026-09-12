<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\RoleAssignmentAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\OperationalReadinessFixtures;
use Tests\Support\OperationalReadinessMetrics;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use OperationalReadinessFixtures;
    use OperationalReadinessMetrics;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeOperationalReadinessFixtures();
    }

    protected function tearDown(): void
    {
        $this->resetOperationalReadinessClock();
        parent::tearDown();
    }

    public function test_e2e_finance_assignment_is_audited(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/'.$this->financeUser->id.'/finance-role');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.id', $this->financeUser->id)
            ->assertJsonPath('data.role', 'finance');

        $this->assertSame('finance', $this->financeUser->fresh()->role, 'The target must have exactly the single finance role.');
        $this->assertSame(1, RoleAssignmentAudit::where('target_user_id', $this->financeUser->id)->count(), 'Finance assignment must create exactly one audit.');
        $this->assertDatabaseHas('role_assignment_audits', [
            'target_user_id' => $this->financeUser->id,
            'actor_user_id' => $this->admin->id,
            'from_role' => 'sales',
            'to_role' => 'finance',
            'action' => RoleAssignmentAudit::ASSIGNED,
        ]);
    }

    public function test_e2e_removed_finance_role_denies_old_token(): void
    {
        $financeToken = $this->assignFinanceAndLogin();
        $this->withToken($financeToken)->getJson('/api/finance/access')
            ->assertOk()->assertJsonPath('data.role', 'finance');

        $this->withToken($this->adminToken)
            ->deleteJson('/api/admin/users/'.$this->financeUser->id.'/finance-role')
            ->assertOk()
            ->assertJsonPath('data.role', 'outlet');

        $this->withToken($financeToken)->getJson('/api/finance/access')
            ->assertForbidden()->assertJsonPath('message', 'Unauthorized. Finance role required.');
        $this->assertDatabaseHas('role_assignment_audits', [
            'target_user_id' => $this->financeUser->id,
            'actor_user_id' => $this->admin->id,
            'from_role' => 'finance',
            'to_role' => 'outlet',
            'action' => RoleAssignmentAudit::REMOVED,
        ]);
    }

    public function test_e2e_approval_retry_creates_one_invoice(): void
    {
        $this->setPaymentTerm(14);
        $order = $this->createOrderThroughHttp('approval-retry', 1000000);

        $first = $this->approve($order);
        $second = $this->approve($order);
        $invoice = Invoice::where('order_id', $order->id)->sole();

        $first->assertOk()
            ->assertJsonPath('data.status', 'Confirmed')
            ->assertJsonPath('data.invoice.id', $invoice->id)
            ->assertJsonPath('data.invoice.status', Invoice::UNPAID);
        $second->assertOk()
            ->assertJsonPath('data.invoice.id', $invoice->id)
            ->assertJsonPath('data.invoice.invoice_number', $invoice->invoice_number);
        $this->assertEquals(14, $invoice->issue_date->diffInDays($invoice->due_date), 'The configured term must determine the immutable due date.');
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count(), 'Approval retry must reuse one invoice.');
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'Confirmed')->count(), 'Approval retry must append one confirmed transition.');

        $history = $this->withToken($this->outletToken)->getJson('/api/orders/'.$order->id)
            ->assertOk()->assertJsonPath('data.status', 'Confirmed')->json('data.status_history');
        $this->assertSame(['New', 'Confirmed'], collect($history)->pluck('status')->all());
    }

    public function test_e2e_delivery_rejects_missing_proof(): void
    {
        [$order] = $this->createApprovedOrder('missing-proof');
        $delivery = $this->startDelivery($order);

        $this->withToken($this->driverToken)
            ->patchJson('/api/deliveries/'.$delivery->id.'/status', ['status' => Delivery::DELIVERED])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['recipient_name', 'proof_of_delivery_url']);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => Delivery::IN_PROGRESS,
            'recipient_name' => null,
            'proof_of_delivery_url' => null,
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
        $this->assertDatabaseMissing('delivery_status_histories', [
            'delivery_id' => $delivery->id,
            'status' => Delivery::DELIVERED,
        ]);
    }

    public function test_e2e_delivery_and_partial_payment(): void
    {
        $financeToken = $this->assignFinanceAndLogin();
        [$order, $invoice] = $this->createApprovedOrder('delivery-partial');
        $dueDate = $invoice->due_date->toDateString();
        $delivery = $this->startDelivery($order);

        $this->deliver($delivery)->assertOk()
            ->assertJsonPath('data.status', Delivery::DELIVERED)
            ->assertJsonPath('data.recipient_name', 'T10 recipient')
            ->assertJsonPath('data.proof_of_delivery_url', 'https://example.test/t10-proof.jpg')
            ->assertJsonPath('data.order.status', 'Delivered');

        $payment = $this->postPayment($financeToken, $order, 300000, 't10-partial-payment');
        $payment->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '300000.00')
            ->assertJsonPath('data.order.status', 'Partially Paid')
            ->assertJsonPath('data.order.outstanding_balance', '700000.00');

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'status' => Delivery::DELIVERED,
            'recipient_name' => 'T10 recipient',
            'proof_of_delivery_url' => 'https://example.test/t10-proof.jpg',
        ]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'amount' => 300000, 'status' => 'completed']);
        $invoice->refresh();
        $this->assertSame(Invoice::PARTIALLY_PAID, $invoice->status);
        $this->assertSame('300000.00', (string) $invoice->paid_amount);
        $this->assertSame('700000.00', (string) $invoice->balance_amount);
        $this->assertSame($dueDate, $invoice->due_date->toDateString(), 'Partial payment must preserve the due date.');
    }

    public function test_e2e_full_payment_and_cancellation_guard(): void
    {
        $financeToken = $this->assignFinanceAndLogin();
        [$order, $invoice] = $this->createApprovedOrder('full-payment');
        $this->deliver($this->startDelivery($order))->assertOk();
        $this->postPayment($financeToken, $order, 300000, 't10-first-payment')->assertCreated();

        $this->postPayment($financeToken, $order, 700000, 't10-final-payment')
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'Paid')
            ->assertJsonPath('data.order.paid_amount', '1000000.00')
            ->assertJsonPath('data.order.outstanding_balance', '0.00');
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::PAID,
            'paid_amount' => 1000000,
            'balance_amount' => 0,
        ]);

        $this->withToken($this->adminToken)->putJson('/api/orders/'.$order->id.'/cancel')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Orders with payment rows cannot be cancelled.');
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Paid']);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => Invoice::PAID, 'balance_amount' => 0]);
    }

    public function test_e2e_reminder_retry_is_bounded_and_immutable(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        $financeToken = $this->assignFinanceAndLogin();
        $this->setPaymentTerm(1);
        [, $invoice] = $this->createApprovedOrder('reminder-failure');
        $before = [
            ...$invoice->only(['status', 'paid_amount', 'balance_amount']),
            'due_date' => $invoice->due_date->toDateString(),
        ];
        $failingClient = $this->useFailingWhatsAppClient();

        foreach ([0, 1, 5, 15] as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta')->addMinutes($minutes));
            $this->artisan('invoices:reminders')->assertExitCode(0)->run();
        }

        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $this->assertSame(InvoiceReminder::FAILED, $reminder->status);
        $this->assertSame(4, $reminder->attempts, 'One attempt plus three bounded retries must be recorded.');
        $this->assertNotNull($reminder->failed_at, 'Final provider failure must be audited.');
        $this->assertSame('T10 provider unavailable', $reminder->last_error);
        $this->assertNull($reminder->next_attempt_at);
        $this->assertSame([$reminder->idempotency_key, $reminder->idempotency_key, $reminder->idempotency_key, $reminder->idempotency_key], $failingClient->idempotencyKeys);

        Carbon::setTestNow(Carbon::parse('2026-09-10 08:00:00', 'Asia/Jakarta'));
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();
        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count(), 'Repeated processing must retain one reminder identity.');
        $this->assertSame(4, $reminder->fresh()->attempts, 'Final failure must not retry without bound.');
        $invoice->refresh();
        $after = [
            ...$invoice->only(['status', 'paid_amount', 'balance_amount']),
            'due_date' => $invoice->due_date->toDateString(),
        ];
        $this->assertSame($before, $after, 'Reminder failure must not mutate the invoice.');

        $this->withToken($financeToken)->getJson('/api/finance/reminders?page=1&limit=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reminder->id)
            ->assertJsonPath('data.0.status', InvoiceReminder::FAILED)
            ->assertJsonPath('data.0.attempts', 4)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_e2e_metrics_and_histories_are_bounded_scoped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $this->adminToken = $this->login($this->admin);
        $this->outletToken = $this->login($this->outletUser);
        $financeToken = $this->assignFinanceAndLogin();
        $records = $this->seedOperationalMetricsHistory();

        $this->assertOperationalMetricsForOutlet($financeToken);
        $this->assertFinanceHistoriesAreBounded($financeToken, $records);
        $this->assertOutletHistoriesAreScoped($records);
        $this->assertEmptyOutletMetricsAreZeroSafe();
    }

    public function test_e2e_retries_do_not_duplicate_operational_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        $financeToken = $this->assignFinanceAndLogin();
        $this->setPaymentTerm(1);
        $order = $this->createOrderThroughHttp('all-retries', 1000000);
        $firstApproval = $this->approve($order)->assertOk();
        $secondApproval = $this->approve($order)->assertOk();
        $this->assertSame($firstApproval->json('data.invoice.id'), $secondApproval->json('data.invoice.id'));
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'Confirmed')->count());

        $this->deliver($this->startDelivery($order))->assertOk();
        $payloadKey = 't10-replayed-payment';
        $firstPayment = $this->postPayment($financeToken, $order, 300000, $payloadKey)->assertCreated();
        $replayedPayment = $this->postPayment($financeToken, $order, 300000, $payloadKey)->assertOk();
        $this->assertSame($firstPayment->json('data.id'), $replayedPayment->json('data.id'));
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'status' => Invoice::PARTIALLY_PAID,
            'paid_amount' => 300000,
            'balance_amount' => 700000,
        ]);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'Partially Paid')->count());

        $callsBeforeReminder = count($this->whatsAppClient->idempotencyKeys);
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();
        $reminder = InvoiceReminder::where('invoice_id', $firstApproval->json('data.invoice.id'))->sole();
        $this->assertSame(InvoiceReminder::SENT, $reminder->status);
        $this->assertSame(1, $reminder->attempts);
        $this->assertSame($callsBeforeReminder + 1, count($this->whatsAppClient->idempotencyKeys), 'Reminder retry must not call the provider twice.');
        $this->assertSame($reminder->idempotency_key, $this->whatsAppClient->idempotencyKeys[$callsBeforeReminder]);
    }
}
