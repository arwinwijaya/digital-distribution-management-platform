<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InvoiceConcurrencyHarness;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;
    protected ?InvoiceConcurrencyHarness $raceHarness = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'invoice-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create(['user_id' => $this->outletUser->id, 'is_active' => true]);
        $this->outletToken = $this->login($this->outletUser);
        $admin = User::factory()->admin()->create([
            'email' => 'invoice-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($admin);
    }

    protected function tearDown(): void
    {
        $this->raceHarness?->close();
        parent::tearDown();
    }

    public function test_approved_order_creates_invoice_with_configured_term(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $order = $this->createOrder('configured-term');
        $this->approve($order)->assertOk();

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(14, Carbon::parse($invoice->issue_date)->diffInDays(Carbon::parse($invoice->due_date)));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame((string) $order->total_amount, (string) $invoice->balance_amount);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_missing_payment_term_uses_seven_day_fallback(): void
    {
        $this->outlet->update(['payment_term_days' => null]);
        $order = $this->createOrder('fallback-term');
        $this->approve($order)->assertOk();

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(7, Carbon::parse($invoice->issue_date)->diffInDays(Carbon::parse($invoice->due_date)));
    }

    public function test_term_changes_affect_new_invoices_only(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $firstOrder = $this->createOrder('first-term');
        $this->approve($firstOrder)->assertOk();
        $firstDueDate = Invoice::where('order_id', $firstOrder->id)->value('due_date');

        $this->outlet->update(['payment_term_days' => 30]);
        $secondOrder = $this->createOrder('second-term');
        $this->approve($secondOrder)->assertOk();

        $this->assertEquals($firstDueDate, Invoice::where('order_id', $firstOrder->id)->value('due_date'));
        $second = Invoice::where('order_id', $secondOrder->id)->sole();
        $this->assertEquals(30, Carbon::parse($second->issue_date)->diffInDays(Carbon::parse($second->due_date)));
    }

    public function test_approval_retry_reuses_invoice(): void
    {
        $order = $this->createOrder('approval-retry');
        $this->approve($order)->assertOk();
        $this->approve($order)->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'Confirmed')->count());
    }

    public function test_concurrent_approvals_create_one_invoice(): void
    {
        $this->raceHarness = new InvoiceConcurrencyHarness();
        $this->raceHarness->prepare();
        $order = $this->raceHarness->createOrderFixture();
        $this->raceHarness->startServers();

        $responses = $this->raceHarness->runConcurrentApprovals($order->id);
        $this->assertSame([200, 200], array_values(array_column($responses, 'status')));
        $this->assertSame(1, Invoice::on(InvoiceConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::on(InvoiceConcurrencyHarness::CONNECTION)->where('order_id', $order->id)->where('status', 'Confirmed')->count());
    }

    public function test_invoice_history_is_bounded_and_scoped(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $first = $this->createOrder('history-a-1');
        $second = $this->createOrder('history-a-2');
        $this->approve($first)->assertOk();
        $this->approve($second)->assertOk();

        $otherUser = User::factory()->outlet()->create([
            'email' => 'invoice-other@example.test',
            'password' => Hash::make('password123'),
        ]);
        $otherOutlet = Outlet::factory()->create(['user_id' => $otherUser->id]);
        $otherOrder = $this->createOrderForOutlet($otherOutlet, 'history-b-1');
        $this->approve($otherOrder)->assertOk();

        $response = $this->withToken($this->outletToken)->getJson('/api/invoices?page=1&limit=1');
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(1, 'data');
        $this->assertSame($this->outlet->id, $response->json('data.0.outlet_id'));
    }

    public function test_invalid_invoice_pagination_is_rejected(): void
    {
        $this->withToken($this->outletToken)->getJson('/api/invoices?page=0&limit=101')
            ->assertStatus(422)->assertJsonValidationErrors(['page', 'limit']);
    }

    public function test_admin_can_cancel_unpaid_invoice(): void
    {
        $order = $this->createOrder('cancel-unpaid');
        $this->approve($order)->assertOk();
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/cancel")->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Cancelled']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::CANCELLED]);
    }

    public function test_payment_row_prevents_cancellation(): void
    {
        $order = $this->createOrder('cancel-payment');
        $this->approve($order)->assertOk();
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 1,
            'payment_method' => 'cash', 'status' => 'pending', 'idempotency_key' => 'cancel-payment-row',
        ]);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/cancel")->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::UNPAID]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
    }

    public function test_non_admin_cannot_cancel_order(): void
    {
        $order = $this->createOrder('cancel-forbidden');
        $this->approve($order)->assertOk();
        $this->withToken($this->outletToken)->putJson("/api/orders/{$order->id}/cancel")->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::UNPAID]);
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->json('data.token');
    }

    protected function createOrder(string $identity): Order
    {
        return $this->createOrderForOutlet($this->outlet, $identity);
    }

    protected function createOrderForOutlet(Outlet $outlet, string $identity): Order
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 100, 'is_active' => true]);
        $user = $outlet->user;
        $token = $user->id === $this->outletUser->id ? $this->outletToken : $this->login($user);
        $response = $this->withToken($token)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $identity,
        ])->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    protected function approve(Order $order): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve");
    }
}
