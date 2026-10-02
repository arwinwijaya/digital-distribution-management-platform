<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\DummyModeService;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixture for dummy mode.
 *
 * Seeds one overdue, partially-paid invoice (`INV-DUMMY-0001`) with two line
 * items (frozen `product_name_snapshot`) and three payments covering every
 * status, then pre-generates the invoice detail payload so dummy-mode requests
 * never touch the live query path.
 *
 * Re-running the seeder is safe: rows are looked up by their stable natural
 * keys and the cached payload is rewritten.
 */
class DummySeeder extends Seeder
{
    public const INVOICE_NUMBER = 'INV-DUMMY-0001';

    private const OUTLET_EMAIL = 'dummy.outlet@ddp.test';
    private const DEFAULT_PASSWORD = 'password123';

    public function run(): void
    {
        $outletUser = User::firstOrCreate(
            ['email' => self::OUTLET_EMAIL],
            [
                'name' => 'Dummy Outlet',
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => 'outlet',
                'phone' => '081200000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $outlet = Outlet::firstOrCreate(
            ['user_id' => $outletUser->id],
            [
                'name' => 'Toko Dummy Sejahtera',
                'phone' => '02150000001',
                'address' => 'Jl. Dummy No. 1',
                'city' => 'Jakarta',
                'district' => 'Menteng',
                'latitude' => -6.1950,
                'longitude' => 106.8300,
                'is_active' => true,
                'payment_term_days' => 7,
                'category' => 'toko_kelontong',
            ]
        );

        $products = [
            ['name' => 'Minyak Goreng 2L', 'price' => 25000, 'sku' => 'DUMMY-SKU-0001', 'quantity' => 2],
            ['name' => 'Beras Premium 5kg', 'price' => 15000, 'sku' => 'DUMMY-SKU-0002', 'quantity' => 3],
        ];

        $totalAmount = 0.0;
        $items = [];
        foreach ($products as $productData) {
            $product = Product::firstOrCreate(
                ['sku' => $productData['sku']],
                [
                    'name' => $productData['name'],
                    'description' => null,
                    'price' => $productData['price'],
                    'stock_quantity' => 100,
                    'category' => 'food',
                    'is_active' => true,
                ]
            );

            $subtotal = (float) $product->price * $productData['quantity'];
            $totalAmount += $subtotal;

            $items[] = [
                'product' => $product,
                'quantity' => $productData['quantity'],
                'unit_price' => (float) $product->price,
                'subtotal' => $subtotal,
            ];
        }

        // Overdue: due date in the past with an outstanding balance.
        $issueDate = Carbon::today()->subDays(14);
        $dueDate = Carbon::today()->subDays(7);

        $order = Order::firstOrCreate(
            ['order_id' => 'DUMMY-ORDER-0001'],
            [
                'outlet_id' => $outlet->id,
                'status' => 'Approved',
                'due_date' => $dueDate->toDateString(),
                'total_amount' => $totalAmount,
                'paid_amount' => 30000,
                'commission_percentage' => 2.00,
                'idempotency_key' => 'dummy-order-0001',
            ]
        );

        foreach ($items as $item) {
            OrderItem::firstOrCreate(
                ['order_id' => $order->id, 'product_id' => $item['product']->id],
                [
                    'product_name_snapshot' => $item['product']->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]
            );
        }

        $invoice = Invoice::firstOrCreate(
            ['invoice_number' => self::INVOICE_NUMBER],
            [
                'order_id' => $order->id,
                'outlet_id' => $outlet->id,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'total_amount' => $totalAmount,
                'paid_amount' => 30000,
                'balance_amount' => $totalAmount - 30000,
                'status' => Invoice::PARTIALLY_PAID,
            ]
        );

        $this->seedPayments($order, $outlet);

        $invoice->refresh();

        // Pre-generate the deterministic payload exactly once.
        $detail = app(InvoiceService::class)->getDetail($invoice);
        Cache::forever(DummyModeService::DETAIL_CACHE_KEY, $detail);
    }

    /**
     * Three payments, one per status, with deterministic descending timestamps
     * so "newest first" ordering is stable.
     */
    private function seedPayments(Order $order, Outlet $outlet): void
    {
        $base = Carbon::today()->subDays(3);

        $definitions = [
            ['status' => 'failed', 'amount' => 5000, 'method' => 'cash', 'days' => 0],
            ['status' => 'pending', 'amount' => 10000, 'method' => 'transfer', 'days' => 1],
            ['status' => 'completed', 'amount' => 30000, 'method' => 'transfer', 'days' => 2],
        ];

        foreach ($definitions as $definition) {
            $payment = Payment::firstOrCreate(
                ['idempotency_key' => 'dummy-payment-'.$definition['status']],
                [
                    'order_id' => $order->id,
                    'outlet_id' => $outlet->id,
                    'amount' => $definition['amount'],
                    'payment_method' => $definition['method'],
                    'status' => $definition['status'],
                    'receipt_reference' => 'DUMMY-'.strtoupper($definition['status']),
                ]
            );

            $timestamp = $base->copy()->addDays($definition['days']);
            $payment->created_at = $timestamp;
            $payment->updated_at = $timestamp;
            $payment->saveQuietly();
        }
    }
}