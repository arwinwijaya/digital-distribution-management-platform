<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Outlet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoicePdfPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InvoiceTemplateSeeder::class);

        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'pdf-perf-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create(['user_id' => $this->outletUser->id, 'is_active' => true]);
        $this->outletToken = $this->login($this->outletUser);
        $admin = User::factory()->admin()->create([
            'email' => 'pdf-perf-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($admin);
    }

    public function test_normal_invoice_pdf_generation_under_5_seconds(): void
    {
        // Given: a normal invoice (<100 line items, <10 payments)
        $invoiceId = $this->createNormalInvoice();

        // When: GET /invoices/{id}/pdf
        $start = microtime(true);
        $response = $this->withToken($this->outletToken)
            ->get("/api/invoices/{$invoiceId}/pdf");
        $elapsed = microtime(true) - $start;

        // Then: response time < 5 seconds
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertLessThan(
            5.0,
            $elapsed,
            "PDF generation took {$elapsed}s, expected < 5s for a normal invoice"
        );
    }

    /**
     * Create a normal invoice: 50 line items (below the 100 limit) and
     * 5 payments (below the 10 limit).
     */
    protected function createNormalInvoice(): int
    {
        // Create 50 products
        $items = [];
        for ($i = 1; $i <= 50; $i++) {
            $product = Product::factory()->create([
                'name' => "Product {$i}",
                'price' => 10000 + ($i * 100),
                'stock_quantity' => 1000,
                'is_active' => true,
            ]);
            $items[] = ['product_id' => $product->id, 'quantity' => $i % 5 + 1];
        }

        $response = $this->withToken($this->outletToken)->postJson('/api/orders', [
            'items' => $items,
            'idempotency_key' => 'pdf-perf-'.uniqid(),
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();

        $invoiceId = Invoice::where('order_id', $order->id)->value('id');

        // Add 5 payments
        for ($i = 1; $i <= 5; $i++) {
            Payment::create([
                'order_id' => $order->id,
                'outlet_id' => $order->outlet_id,
                'amount' => 1000 * $i,
                'payment_method' => 'cash',
                'status' => 'completed',
                'idempotency_key' => 'pdf-perf-pay-'.uniqid()."-{$i}",
            ]);
        }

        return $invoiceId;
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }
}