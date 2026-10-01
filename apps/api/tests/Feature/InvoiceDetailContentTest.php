<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceDetailContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'detail-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create(['user_id' => $this->outletUser->id, 'is_active' => true]);
        $this->outletToken = $this->login($this->outletUser);
        $admin = User::factory()->admin()->create([
            'email' => 'detail-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($admin);
    }

    public function test_frozen_product_name_snapshot_returned(): void
    {
        $product = Product::factory()->create(['name' => 'Sabun A', 'price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();

        // Rename product after invoice creation
        $product->update(['name' => 'Sabun B']);

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');

        $this->assertCount(1, $data['line_items']);
        $this->assertEquals('Sabun A', $data['line_items'][0]['product_name']);
        $this->assertEquals('Sabun A', $data['line_items'][0]['product_name_snapshot']);
        // Also fallback alias `items` should match
        $this->assertEquals('Sabun A', $data['items'][0]['product_name']);
    }

    public function test_all_payment_statuses_newest_first(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();

        // Create three payments with distinct created_at so ordering is deterministic
        $base = Carbon::now();
        $p1 = Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 3000,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'pay-cmpl-'.uniqid(),
        ]);
        $p1->created_at = $base->copy()->subDays(3);
        $p1->updated_at = $p1->created_at;
        $p1->saveQuietly();

        $p2 = Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 2000,
            'payment_method' => 'transfer', 'status' => 'pending', 'idempotency_key' => 'pay-pend-'.uniqid(),
        ]);
        $p2->created_at = $base->copy()->subDays(1);
        $p2->updated_at = $p2->created_at;
        $p2->saveQuietly();

        $p3 = Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 1000,
            'payment_method' => 'cash', 'status' => 'failed', 'idempotency_key' => 'pay-fail-'.uniqid(),
        ]);
        $p3->created_at = $base->copy()->subDays(2);
        $p3->updated_at = $p3->created_at;
        $p3->saveQuietly();

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');
        $this->assertCount(3, $data['payments']);
        // newest first: p2 (1 day ago), p3 (2 days ago), p1 (3 days ago)
        $this->assertEquals($p2->id, $data['payments'][0]['id']);
        $this->assertEquals($p3->id, $data['payments'][1]['id']);
        $this->assertEquals($p1->id, $data['payments'][2]['id']);
        // all statuses present, no filter
        $statuses = array_column($data['payments'], 'status');
        $this->assertContains('completed', $statuses);
        $this->assertContains('pending', $statuses);
        $this->assertContains('failed', $statuses);
    }

    public function test_stored_totals_not_recomputed(): void
    {
        $product = Product::factory()->create(['price' => 50000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product, 2); // 100k
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();
        $invoice->refresh();
        $storedTotal = $invoice->total_amount;
        $storedPaid = $invoice->paid_amount;
        $storedBalance = $invoice->balance_amount;

        // Create a completed payment that would recompute totals if logic were wrong, but stored invoice must stay unchanged
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 40000,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'pay-stored-'.uniqid(),
        ]);

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');
        $this->assertEquals((string) $storedTotal, (string) $data['total_amount']);
        $this->assertEquals((string) $storedPaid, (string) $data['paid_amount']);
        $this->assertEquals((string) $storedBalance, (string) $data['balance_amount']);
    }

    public function test_overdue_badge_true_when_due_passed_and_balance_positive(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();
        $invoice->update(['due_date' => Carbon::yesterday()->toDateString(), 'balance_amount' => 5000]);

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');
        $this->assertTrue((bool) ($data['is_overdue'] ?? $data['overdue']));
    }

    public function test_overdue_badge_false_when_balance_zero_or_due_future(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();

        // Case 1: due yesterday but balance 0 -> not overdue
        $invoice->update(['due_date' => Carbon::yesterday()->toDateString(), 'balance_amount' => 0]);
        $data1 = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk()->json('data');
        $this->assertFalse((bool) ($data1['is_overdue'] ?? $data1['overdue']));

        // Case 2: due tomorrow and balance >0 -> not overdue
        $invoice->update(['due_date' => Carbon::tomorrow()->toDateString(), 'balance_amount' => 5000]);
        $data2 = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk()->json('data');
        $this->assertFalse((bool) ($data2['is_overdue'] ?? $data2['overdue']));
    }

    public function test_cancelled_status_retained_and_totals_stored(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();
        $invoice->update(['status' => Invoice::CANCELLED, 'total_amount' => 10000, 'paid_amount' => 0, 'balance_amount' => 0]);

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');
        $this->assertEquals(Invoice::CANCELLED, $data['status']);
        $this->assertEquals('10000.00', number_format((float) $data['total_amount'], 2, '.', ''));
        $this->assertEquals('0.00', number_format((float) $data['balance_amount'], 2, '.', ''));
        // header/outlet info present
        $this->assertNotNull($data['outlet']);
        $this->assertEquals($this->outlet->id, $data['outlet']['id']);
    }

    public function test_json_shape_contains_header_line_items_payments_outlet(): void
    {
        $product = Product::factory()->create(['price' => 10000, 'stock_quantity' => 50, 'is_active' => true]);
        $order = $this->createOrderWithProduct($product);
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();
        $invoice = Invoice::where('order_id', $order->id)->firstOrFail();

        $resp = $this->withToken($this->outletToken)->getJson("/api/invoices/{$invoice->id}")->assertOk();
        $data = $resp->json('data');
        $this->assertArrayHasKey('invoice_number', $data);
        $this->assertArrayHasKey('issue_date', $data);
        $this->assertArrayHasKey('due_date', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('line_items', $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertArrayHasKey('outlet', $data);
    }

    protected function createOrderWithProduct(Product $product, int $qty = 1): Order
    {
        $resp = $this->withToken($this->outletToken)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
            'idempotency_key' => 'detail-content-'.uniqid(),
        ])->assertCreated();
        return Order::findOrFail($resp->json('data.id'));
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }
}
