<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppClient;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RoleAssignmentAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $outletUser;
    private User $financeUser;
    private User $driver;
    private Outlet $outlet;
    private string $adminToken;
    private string $outletToken;
    private string $driverToken;
    private object $whatsAppClient;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));
        config(['whatsapp.enabled' => true]);
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

        $this->admin = $this->user('admin', 't10-admin@example.test');
        $this->outletUser = $this->user('outlet', 't10-outlet@example.test');
        $this->financeUser = $this->user('sales', 't10-finance@example.test');
        $this->driver = $this->user('driver', 't10-driver@example.test');
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'phone' => '+628123456789',
            'is_active' => true,
        ]);

        $this->adminToken = $this->login($this->admin);
        $this->outletToken = $this->login($this->outletUser);
        $this->driverToken = $this->login($this->driver);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
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
        [, $openInvoice] = $this->seedInvoice($this->outlet, 'own-open', '2026-09-10', '2026-09-25', 100, 0, Invoice::UNPAID);
        [$paidOrder, $paidInvoice] = $this->seedInvoice($this->outlet, 'own-paid', '2026-09-01', '2026-09-10', 100, 100, Invoice::PAID);
        $ownPayment = $this->seedPayment($paidOrder, 100, 'own-payment', '2026-09-05 10:00:00');
        $ownSentReminder = $this->seedReminder($openInvoice, InvoiceReminder::SENT, 'own-reminder-sent', '2026-09-15');
        $this->seedReminder($openInvoice, InvoiceReminder::FAILED, 'own-reminder-failed', '2026-09-16');

        $otherOutlet = Outlet::factory()->create(['phone' => '+628199999999', 'is_active' => true]);
        [$otherOrder, $otherInvoice] = $this->seedInvoice($otherOutlet, 'other', '2026-09-10', '2026-09-19', 900, 100, Invoice::PARTIALLY_PAID);
        $otherPayment = $this->seedPayment($otherOrder, 100, 'other-payment', '2026-09-12 10:00:00');
        $this->seedReminder($otherInvoice, InvoiceReminder::SENT, 'other-reminder', '2026-09-17');

        $metrics = $this->withToken($financeToken)->getJson('/api/finance/metrics?outlet_id='.$this->outlet->id);
        $metrics->assertOk()->assertJsonStructure(['data' => [
            'issued_invoices', 'outstanding_balance', 'overdue_rate', 'collection_time',
            'payment_status_breakdown', 'reminders',
        ]])
            ->assertJsonPath('data.window.start_date', '2026-08-22')
            ->assertJsonPath('data.window.end_date', '2026-09-20')
            ->assertJsonPath('data.issued_invoices.count', 2)
            ->assertJsonPath('data.outstanding_balance.amount', 100)
            ->assertJsonPath('data.overdue_rate.rate', 0)
            ->assertJsonPath('data.collection_time.average_days', 4)
            ->assertJsonPath('data.collection_time.fully_collected_count', 1)
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 1)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 0)
            ->assertJsonPath('data.payment_status_breakdown.paid', 1)
            ->assertJsonPath('data.payment_status_breakdown.cancelled', 0)
            ->assertJsonPath('data.reminders.success', 1)
            ->assertJsonPath('data.reminders.failure', 1);

        $this->withToken($financeToken)->getJson('/api/invoices?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherInvoice->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.has_more', true);
        $this->withToken($financeToken)->getJson('/api/payments?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $otherPayment->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.has_more', true);
        $this->withToken($financeToken)->getJson('/api/finance/reminders?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownSentReminder->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.has_more', true);

        $invoiceHistory = $this->withToken($this->outletToken)->getJson('/api/invoices?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $paymentHistory = $this->withToken($this->outletToken)->getJson('/api/payments?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 1)->json('data');
        $reminderHistory = $this->withToken($this->outletToken)->getJson('/api/finance/reminders?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $this->assertTrue(collect($invoiceHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Invoice history must exclude another outlet.');
        $this->assertTrue(collect($paymentHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Payment history must exclude another outlet.');
        $this->assertTrue(collect($reminderHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Reminder history must exclude another outlet.');
        $this->assertSame([$paidInvoice->id, $openInvoice->id], collect($invoiceHistory)->pluck('id')->all(), 'Invoice history ordering must be stable.');
        $this->assertSame([$ownPayment->id], collect($paymentHistory)->pluck('id')->all(), 'Payment history ordering must be stable.');
        $this->assertNotContains($otherInvoice->id, collect($invoiceHistory)->pluck('id')->all());

        $emptyUser = $this->user('outlet', 't10-empty-outlet@example.test');
        Outlet::factory()->create(['user_id' => $emptyUser->id, 'phone' => '+628177777777', 'is_active' => true]);
        $this->withToken($this->login($emptyUser))->getJson('/api/finance/metrics')
            ->assertOk()
            ->assertJsonPath('data.issued_invoices.count', 0)
            ->assertJsonPath('data.outstanding_balance.amount', 0)
            ->assertJsonPath('data.overdue_rate.rate', 0)
            ->assertJsonPath('data.collection_time.average_days', 0)
            ->assertJsonPath('data.collection_time.fully_collected_count', 0)
            ->assertJsonPath('data.payment_status_breakdown.unpaid', 0)
            ->assertJsonPath('data.payment_status_breakdown.partially_paid', 0)
            ->assertJsonPath('data.payment_status_breakdown.paid', 0)
            ->assertJsonPath('data.payment_status_breakdown.cancelled', 0)
            ->assertJsonPath('data.reminders.success', 0)
            ->assertJsonPath('data.reminders.failure', 0);
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

    private function user(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('status', 'success')->json('data.token');
    }

    private function assignFinanceAndLogin(): string
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/admin/users/'.$this->financeUser->id.'/finance-role')
            ->assertOk()->assertJsonPath('data.role', 'finance');

        return $this->login($this->financeUser->fresh());
    }

    private function setPaymentTerm(int $days): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/admin/outlets/'.$this->outlet->id.'/payment-terms', ['payment_term_days' => $days])
            ->assertOk()->assertJsonPath('data.payment_term_days', $days);
    }

    private function createOrderThroughHttp(string $key, int $total): Order
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
    private function createApprovedOrder(string $key, int $total = 1000000): array
    {
        $order = $this->createOrderThroughHttp($key, $total);
        $response = $this->approve($order)->assertOk()->assertJsonPath('data.status', 'Confirmed');
        $invoice = Invoice::findOrFail($response->json('data.invoice.id'));

        return [$order->fresh(), $invoice];
    }

    private function approve(Order $order): TestResponse
    {
        return $this->withToken($this->adminToken)->putJson('/api/orders/'.$order->id.'/approve');
    }

    private function startDelivery(Order $order): Delivery
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

    private function deliver(Delivery $delivery): TestResponse
    {
        return $this->withToken($this->driverToken)->patchJson('/api/deliveries/'.$delivery->id.'/status', [
            'status' => Delivery::DELIVERED,
            'recipient_name' => 'T10 recipient',
            'proof_of_delivery_url' => 'https://example.test/t10-proof.jpg',
        ]);
    }

    private function postPayment(string $token, Order $order, int $amount, string $key): TestResponse
    {
        return $this->withToken($token)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'idempotency_key' => $key,
        ]);
    }

    private function useFailingWhatsAppClient(): object
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

    /** @return array{0: Order, 1: Invoice} */
    private function seedInvoice(Outlet $outlet, string $key, string $issueDate, string $dueDate, int $total, int $paid, string $status): array
    {
        $order = Order::create([
            'order_id' => 'ORD-T10-'.$key,
            'outlet_id' => $outlet->id,
            'status' => $status === Invoice::PAID ? 'Paid' : ($status === Invoice::PARTIALLY_PAID ? 'Partially Paid' : 'Delivered'),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'commission_percentage' => 2,
            'idempotency_key' => 't10-seed-'.$key,
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $outlet->id,
            'invoice_number' => 'INV-T10-'.$key,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_amount' => $total - $paid,
            'status' => $status,
        ]);
        DB::table('invoices')->where('id', $invoice->id)->update([
            'created_at' => Carbon::parse($issueDate, 'Asia/Jakarta'),
            'updated_at' => Carbon::parse($issueDate, 'Asia/Jakarta'),
        ]);

        return [$order, $invoice->fresh()];
    }

    private function seedPayment(Order $order, int $amount, string $key, string $createdAt): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => 'completed',
            'idempotency_key' => 't10-'.$key,
        ]);
        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => Carbon::parse($createdAt, 'Asia/Jakarta'),
            'updated_at' => Carbon::parse($createdAt, 'Asia/Jakarta'),
        ]);

        return $payment->fresh();
    }

    private function seedReminder(Invoice $invoice, string $status, string $key, string $eventDate): InvoiceReminder
    {
        return InvoiceReminder::create([
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => $eventDate,
            'status' => $status,
            'attempts' => 1,
            'sent_at' => $status === InvoiceReminder::SENT ? Carbon::parse($eventDate, 'Asia/Jakarta') : null,
            'failed_at' => $status === InvoiceReminder::FAILED ? Carbon::parse($eventDate, 'Asia/Jakarta') : null,
            'last_error' => $status === InvoiceReminder::FAILED ? 'seeded failure' : null,
            'idempotency_key' => 't10-'.$key,
        ]);
    }
}
