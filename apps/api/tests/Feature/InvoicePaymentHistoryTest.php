<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoicePaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;
    protected string $financeToken;
    protected string $adminToken;
    protected Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finance = User::factory()->create([
            'email' => 'invoice-history-finance@example.test',
            'password' => Hash::make('password123'),
            'role' => 'finance',
        ]);
        $outletUser = User::factory()->outlet()->create([
            'email' => 'invoice-history-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
        $this->financeToken = $this->postJson('/api/auth/login', [
            'email' => $this->finance->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
        $admin = User::factory()->admin()->create([
            'email' => 'invoice-history-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    public function test_payment_history_is_bounded_and_scoped(): void
    {
        [$firstOrder] = $this->deliveredInvoice();
        [$secondOrder] = $this->deliveredInvoice();
        Payment::create([
            'order_id' => $firstOrder->id, 'outlet_id' => $firstOrder->outlet_id, 'amount' => 100,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'history-a-1',
        ]);
        Payment::create([
            'order_id' => $secondOrder->id, 'outlet_id' => $secondOrder->outlet_id, 'amount' => 200,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'history-a-2',
        ]);
        $otherUser = User::factory()->outlet()->create([
            'email' => 'invoice-payment-other@example.test',
            'password' => Hash::make('password123'),
        ]);
        $otherOutlet = Outlet::factory()->create(['user_id' => $otherUser->id, 'is_active' => true]);
        $otherOrder = Order::create([
            'order_id' => 'ORD-HISTORY-OTHER-'.uniqid(), 'outlet_id' => $otherOutlet->id,
            'status' => 'Delivered', 'total_amount' => 1000, 'paid_amount' => 0,
            'commission_percentage' => 2, 'idempotency_key' => 'history-other-order-'.uniqid(),
        ]);
        Payment::create([
            'order_id' => $otherOrder->id, 'outlet_id' => $otherOutlet->id, 'amount' => 300,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'history-b-1',
        ]);

        $finance = $this->withToken($this->financeToken)->getJson('/api/payments?page=1&limit=1');
        $finance->assertOk()->assertJsonPath('meta.page', 1)->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)->assertJsonCount(1, 'data');
        $this->withToken($this->adminToken)->getJson('/api/payments?page=1&limit=2')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
        $outlet = User::findOrFail($this->outlet->user_id);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outlet->email, 'password' => 'password123',
        ])->assertOk()->json('data.token');
        $this->withToken($outletToken)->getJson('/api/payments?page=1&limit=10')
            ->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
        $this->assertNotContains($otherOutlet->id, $this->withToken($outletToken)
            ->getJson('/api/payments?page=1&limit=10')->json('data.*.outlet_id'));
    }

    public function test_invalid_payment_pagination_is_rejected(): void
    {
        $this->withToken($this->financeToken)->getJson('/api/payments?page=0&limit=101')
            ->assertStatus(422)->assertJsonValidationErrors(['page', 'limit']);
    }

    /** @return array{0: Order, 1: Invoice} */
    protected function deliveredInvoice(int $total = 1000000): array
    {
        $order = Order::create([
            'order_id' => 'ORD-INVOICE-'.uniqid(),
            'outlet_id' => $this->outlet->id,
            'status' => 'Delivered',
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'order-invoice-'.uniqid(),
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-'.uniqid(),
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => $total,
            'paid_amount' => 0,
            'balance_amount' => $total,
            'status' => Invoice::UNPAID,
        ]);

        return [$order, $invoice];
    }
}
