<?php

namespace Tests\Feature\Concerns;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait InvoicePaymentFixture
{
    protected User $finance;
    protected string $financeToken;
    protected string $adminToken;
    protected Outlet $outlet;

    protected function initInvoicePaymentFixture(string $financeEmail, string $outletEmail, string $adminEmail): void
    {
        $this->finance = User::factory()->create([
            'email' => $financeEmail,
            'password' => Hash::make('password123'),
            'role' => 'finance',
        ]);
        $outletUser = User::factory()->outlet()->create([
            'email' => $outletEmail,
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
            'email' => $adminEmail,
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
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
