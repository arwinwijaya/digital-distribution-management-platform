<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Outlet;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceDetailAuthorizationTest extends TestCase
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

    public function test_invoice_detail_authorization_matrix(): void
    {
        // Create invoice for outlet user
        $invoiceId = $this->createInvoiceForOutlet($this->outlet);
        $this->assertNotNull($invoiceId);

        // Create another outlet and invoice for it
        $otherUser = User::factory()->outlet()->create([
            'email' => 'invoice-other@example.test',
            'password' => Hash::make('password123'),
        ]);
        $otherOutlet = Outlet::factory()->create(['user_id' => $otherUser->id, 'is_active' => true]);
        $otherInvoiceId = $this->createInvoiceForOutlet($otherOutlet);
        $this->assertNotNull($otherInvoiceId);

        // 1. GET /invoices/{own} -> 200
        $this->withToken($this->outletToken)
            ->getJson("/api/invoices/{$invoiceId}")
            ->assertOk();

        // 2. GET /invoices/{other} -> 403
        $this->withToken($this->outletToken)
            ->getJson("/api/invoices/{$otherInvoiceId}")
            ->assertForbidden();

        // 3. no token GET /invoices/{own} -> 401
        $this->withHeaders(['Authorization' => ''])->getJson("/api/invoices/{$invoiceId}")
            ->assertUnauthorized();

        // 4. valid token GET /invoices/{non-existent} -> 404
        $this->withToken($this->outletToken)
            ->getJson('/api/invoices/555555')
            ->assertNotFound();
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function createInvoiceForOutlet(Outlet $outlet): int
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 100, 'is_active' => true]);
        $user = $outlet->user;
        $token = $user->id === $this->outletUser->id ? $this->outletToken : $this->login($user);
        $response = $this->withToken($token)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'test-invoice-'.uniqid(),
        ])->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));

        // Approve to create invoice
        $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve")->assertOk();

        // Return invoice id for this order
        return Invoice::where('order_id', $order->id)->value('id');
    }
}