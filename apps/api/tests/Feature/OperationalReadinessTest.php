<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
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

    /**
     * Re-login the finance user to get a fresh JWT after time-travel.
     * Existing tests face the same issue and re-login explicitly (see
     * test_e2e_metrics_and_histories_are_bounded_scoped).
     */
    private function refreshFinanceToken(): string
    {
        return $this->login($this->financeUser->fresh());
    }

    /**
     * Combined operational-readiness scenario that creates its dataset entirely
     * through authenticated HTTP routes and the real invoices:reminders Artisan
     * command, then queries finance metrics plus invoice/payment/reminder
     * histories for those exact produced identities, including a second outlet
     * for isolation and a zero-safe empty outlet boundary.
     *
     * Lifecycle produced records (no model-factory seeding for the primary
     * dataset): two outlet-1 orders approved via HTTP, delivered via the real
     * driver flow, paid via the finance payment route, and reminded via the
     * Artisan scheduler. A second outlet's order is created and settled through
     * the same HTTP surface to prove cross-outlet isolation.
     */
    public function test_e2e_http_driven_dataset_composes_metrics_and_histories(): void
    {
        // ── Phase 1: Approve two outlet-1 orders via HTTP ──────────────────
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        $financeToken = $this->assignFinanceAndLogin();

        [$orderA, $invoiceA] = $this->createApprovedOrder('http-metrics-a', 1000000);
        [$orderB, $invoiceB] = $this->createApprovedOrder('http-metrics-b', 1000000);

        $this->assertSame(Invoice::UNPAID, $invoiceA->status, 'Freshly approved invoice must be unpaid.');
        $this->assertSame(Invoice::UNPAID, $invoiceB->status, 'Freshly approved invoice must be unpaid.');
        $this->assertSame('2026-09-17', $invoiceA->due_date->toDateString(), 'Default 7-day term must set the due date.');
        $this->assertSame('2026-09-17', $invoiceB->due_date->toDateString(), 'Default 7-day term must set the due date.');

        // ── Phase 2: Deliver both orders via the real driver flow ───────────
        $this->deliver($this->startDelivery($orderA))->assertOk();
        $this->deliver($this->startDelivery($orderB))->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderA->id, 'status' => 'Delivered']);
        $this->assertDatabaseHas('orders', ['id' => $orderB->id, 'status' => 'Delivered']);

        // ── Phase 3: Second outlet via HTTP ─────────────────────────────────
        $secondUser = $this->operationalUser('outlet', 't10-second-http@example.test');
        $secondOutlet = Outlet::factory()->create([
            'user_id' => $secondUser->id,
            'phone' => '+628987654321',
            'is_active' => true,
        ]);
        $secondToken = $this->login($secondUser);

        $this->withToken($this->adminToken)
            ->putJson('/api/admin/outlets/'.$secondOutlet->id.'/payment-terms', ['payment_term_days' => 7])
            ->assertOk();

        $productC = Product::factory()->create(['price' => 1000000, 'stock_quantity' => 20, 'is_active' => true]);
        $orderCResponse = $this->withToken($secondToken)->postJson('/api/orders', [
            'items' => [['product_id' => $productC->id, 'quantity' => 1]],
            'idempotency_key' => 't10-http-metrics-c',
        ])->assertCreated();
        $orderC = Order::findOrFail($orderCResponse->json('data.id'));

        $approveC = $this->withToken($this->adminToken)
            ->putJson('/api/orders/'.$orderC->id.'/approve')
            ->assertOk();
        $invoiceC = Invoice::findOrFail($approveC->json('data.invoice.id'));
        $this->assertSame(Invoice::UNPAID, $invoiceC->status);

        $this->deliver($this->startDelivery($orderC))->assertOk();

        // ── Phase 4: Payments via HTTP at controlled time points ────────────
        // Re-login finance user: JWT TTL is 24 h but we travel 2+ days ahead.
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Asia/Jakarta'));
        $financeToken = $this->refreshFinanceToken();

        // Order A: full payment → invoice becomes PAID
        $this->postPayment($financeToken, $orderA, 1000000, 'http-pay-a-full')
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'Paid');
        $invoiceA->refresh();
        $this->assertSame(Invoice::PAID, $invoiceA->status, 'Full payment must advance invoice to paid.');
        $this->assertSame('0.00', (string) $invoiceA->balance_amount);

        // Order B: partial payment → invoice becomes PARTIALLY_PAID
        Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00', 'Asia/Jakarta'));
        $financeToken = $this->refreshFinanceToken();
        $this->postPayment($financeToken, $orderB, 300000, 'http-pay-b-partial')
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'Partially Paid');
        $invoiceB->refresh();
        $this->assertSame(Invoice::PARTIALLY_PAID, $invoiceB->status, 'Partial payment must advance invoice to partially_paid.');
        $this->assertSame('700000.00', (string) $invoiceB->balance_amount);

        // Order C (second outlet): full payment → invoice becomes PAID
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));
        $financeToken = $this->refreshFinanceToken();
        $this->postPayment($financeToken, $orderC, 1000000, 'http-pay-c-full')
            ->assertCreated()
            ->assertJsonPath('data.order.status', 'Paid');
        $invoiceC->refresh();
        $this->assertSame(Invoice::PAID, $invoiceC->status, 'Full payment must advance second-outlet invoice to paid.');

        // ── Phase 5: Real invoices:reminders Artisan command ────────────────
        // At 2026-09-16: today=9/16, tomorrow=9/17. Invoice B (PARTIALLY_PAID,
        // due 9/17) matches h_minus_one candidate. Invoice A and C are PAID and
        // excluded by the candidate selector.
        Carbon::setTestNow(Carbon::parse('2026-09-16 07:00:00', 'Asia/Jakarta'));
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();

        $reminderBH1 = InvoiceReminder::where('invoice_id', $invoiceB->id)
            ->where('event_type', InvoiceReminder::EVENT_H_MINUS_ONE)
            ->sole();
        $this->assertSame(InvoiceReminder::SENT, $reminderBH1->status);
        $this->assertNotNull($reminderBH1->sent_at, 'Sent reminder must record the send timestamp.');
        $this->assertSame(1, $reminderBH1->attempts);

        // At 2026-09-17: today=9/17. Invoice B overdue event fires.
        Carbon::setTestNow(Carbon::parse('2026-09-17 07:00:00', 'Asia/Jakarta'));
        $this->artisan('invoices:reminders')->assertExitCode(0)->run();

        $reminderBOverdue = InvoiceReminder::where('invoice_id', $invoiceB->id)
            ->where('event_type', InvoiceReminder::EVENT_OVERDUE)
            ->sole();
        $this->assertSame(InvoiceReminder::SENT, $reminderBOverdue->status);
        $this->assertNotNull($reminderBOverdue->sent_at, 'Sent reminder must record the send timestamp.');
        $this->assertSame(1, $reminderBOverdue->attempts);

        // Only Invoice B (PARTIALLY_PAID) generated reminders; A and C (PAID)
        // must have zero reminder rows.
        $this->assertSame(0, InvoiceReminder::where('invoice_id', $invoiceA->id)->count(), 'Paid invoice must not produce reminders.');
        $this->assertSame(0, InvoiceReminder::where('invoice_id', $invoiceC->id)->count(), 'Paid second-outlet invoice must not produce reminders.');

        // ── Phase 6: Finance metrics via HTTP for outlet 1 ──────────────────
        // Window: 2026-08-22 → 2026-09-20 (default 30-day from as-of date).
        // Invoices A and B both have issue_date 2026-09-10 (inside window).
        // Invoice A paid on 2026-09-12: collection_time = 2 days.
        // Invoice B partially paid: outstanding balance = 700000.
        // Two sent reminders with event_dates 2026-09-17 and 2026-09-17 (inside window).
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00', 'Asia/Jakarta'));
        $financeToken = $this->refreshFinanceToken();
        $this->adminToken = $this->login($this->admin);
        $this->outletToken = $this->login($this->outletUser);
        $secondToken = $this->login($secondUser);
        $this->withToken($financeToken)->getJson('/api/finance/metrics?outlet_id='.$this->outlet->id)
            ->assertOk()
            ->assertJsonPath('data.window.start_date', '2026-08-22')
            ->assertJsonPath('data.window.end_date', '2026-09-20')
            ->assertJsonPath('data.issued_invoices.count', 2)
            ->assertJsonPath('data.outstanding_balance.amount', 700000)
            ->assertJsonPath('data.overdue_rate.overdue_count', 1)
            ->assertJsonPath('data.overdue_rate.active_count', 1)
            ->assertJsonPath('data.collection_time.average_days', 2)
            ->assertJsonPath('data.collection_time.fully_collected_count', 1)
            ->assertJsonPath('data.payment_status_breakdown.paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 0)
            ->assertJsonPath('data.reminders.success', 2)
            ->assertJsonPath('data.reminders.failure', 0);

        // ── Phase 7: Finance histories are bounded ──────────────────────────
        $this->withToken($financeToken)->getJson('/api/invoices?page=1&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.has_more', true);

        $this->withToken($financeToken)->getJson('/api/payments?page=1&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.has_more', true);

        $this->withToken($financeToken)->getJson('/api/finance/reminders?page=1&limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', true);

        // ── Phase 8: Outlet 1 histories are scoped ──────────────────────────
        $outlet1Invoices = $this->withToken($this->outletToken)
            ->getJson('/api/invoices?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->json('data');
        $this->assertTrue(
            collect($outlet1Invoices)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id),
            'Outlet-1 invoice history must exclude second outlet.'
        );

        $outlet1Payments = $this->withToken($this->outletToken)
            ->getJson('/api/payments?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->json('data');
        $this->assertTrue(
            collect($outlet1Payments)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id),
            'Outlet-1 payment history must exclude second outlet.'
        );

        $outlet1Reminders = $this->withToken($this->outletToken)
            ->getJson('/api/finance/reminders?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->json('data');
        $this->assertTrue(
            collect($outlet1Reminders)->every(fn (array $row) => ($row['outlet_id'] ?? null) === $this->outlet->id),
            'Outlet-1 reminder history must exclude second outlet.'
        );
        $this->assertContains($reminderBH1->id, collect($outlet1Reminders)->pluck('id')->all());
        $this->assertContains($reminderBOverdue->id, collect($outlet1Reminders)->pluck('id')->all());

        // ── Phase 9: Second outlet isolation ────────────────────────────────
        $secondInvoices = $this->withToken($secondToken)
            ->getJson('/api/invoices?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->json('data');
        $this->assertSame($invoiceC->id, $secondInvoices[0]['id'], 'Second outlet must see only its own invoice.');

        $secondPayments = $this->withToken($secondToken)
            ->getJson('/api/payments?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->json('data');
        $this->assertTrue(
            collect($secondPayments)->every(fn (array $row) => $row['outlet_id'] === $secondOutlet->id),
            'Second outlet payment history must be scoped to its own outlet.'
        );

        $this->withToken($secondToken)
            ->getJson('/api/finance/reminders?page=1&limit=100')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.has_more', false);

        $this->withToken($financeToken)
            ->getJson('/api/finance/metrics?outlet_id='.$secondOutlet->id)
            ->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 1)
            ->assertJsonPath('data.outstanding_balance.amount', 0)
            ->assertJsonPath('data.overdue_rate.overdue_count', 0)
            ->assertJsonPath('data.overdue_rate.active_count', 0)
            ->assertJsonPath('data.collection_time.average_days', 5)
            ->assertJsonPath('data.collection_time.fully_collected_count', 1)
            ->assertJsonPath('data.payment_status_breakdown.paid', 1)
            ->assertJsonPath('data.reminders.success', 0)
            ->assertJsonPath('data.reminders.failure', 0);

        // ── Phase 10: Zero-safe empty outlet ────────────────────────────────
        $emptyUser = $this->operationalUser('outlet', 't10-empty-http@example.test');
        Outlet::factory()->create(['user_id' => $emptyUser->id, 'phone' => '+628166666666', 'is_active' => true]);
        $emptyToken = $this->login($emptyUser);
        $this->withToken($emptyToken)->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 0)
            ->assertJsonPath('data.outstanding_balance.amount', 0)
            ->assertJsonPath('data.overdue_rate.rate', 0)
            ->assertJsonPath('data.overdue_rate.overdue_count', 0)
            ->assertJsonPath('data.overdue_rate.active_count', 0)
            ->assertJsonPath('data.collection_time.average_days', 0)
            ->assertJsonPath('data.collection_time.fully_collected_count', 0)
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 0)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 0)
            ->assertJsonPath('data.payment_status_breakdown.paid', 0)
            ->assertJsonPath('data.payment_status_breakdown.cancelled', 0)
            ->assertJsonPath('data.reminders.success', 0)
            ->assertJsonPath('data.reminders.failure', 0);
    }
}
