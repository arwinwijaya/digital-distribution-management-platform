<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InvoiceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_invoices_for_eligible_orders(): void
    {
        $outlet = Outlet::factory()->create(['payment_term_days' => 14]);
        $delivered = $this->order($outlet, 'Delivered', '100.00', 'legacy-delivered');
        $this->payment($delivered, '40.00', 'completed', 'delivered-completed');
        $this->payment($delivered, '0.00', 'completed', 'delivered-zero');
        $this->payment($delivered, '25.00', 'failed', 'delivered-failed');
        $this->payment($delivered, '10.00', 'pending', 'delivered-pending');
        $this->payment($delivered, '-5.00', 'completed', 'delivered-negative');

        $paid = $this->order($outlet, 'Paid', '90.00', 'legacy-paid', ['paid_amount' => '0.00']);
        $this->payment($paid, '90.00', 'completed', 'paid-completed');
        $this->payment($paid, '90.00', 'failed', 'paid-failed');

        $this->artisan('invoices:backfill')
            ->expectsOutputToContain('Created: 2')
            ->assertExitCode(0)
            ->run();

        $deliveredInvoice = Invoice::where('order_id', $delivered->id)->sole();
        $this->assertSame('40.00', (string) $deliveredInvoice->paid_amount);
        $this->assertSame('60.00', (string) $deliveredInvoice->balance_amount);
        $this->assertSame(Invoice::PARTIALLY_PAID, $deliveredInvoice->status);
        $this->assertEquals(14, Carbon::parse($deliveredInvoice->issue_date)->diffInDays(Carbon::parse($deliveredInvoice->due_date)));

        $paidInvoice = Invoice::where('order_id', $paid->id)->sole();
        $this->assertSame('90.00', (string) $paidInvoice->paid_amount);
        $this->assertSame('0.00', (string) $paidInvoice->balance_amount);
        $this->assertSame(Invoice::PAID, $paidInvoice->status);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 7);
    }

    public function test_backfill_skips_ineligible_orders(): void
    {
        $outlet = Outlet::factory()->create();
        $new = $this->order($outlet, 'New', '25.00', 'legacy-new');
        $failed = $this->order($outlet, 'Failed', '30.00', 'legacy-failed');
        $cancelled = $this->order($outlet, 'Cancelled', '35.00', 'legacy-cancelled');

        $this->artisan('invoices:backfill')
            ->expectsOutputToContain('Skipped: 3')
            ->expectsOutputToContain($new->order_id)
            ->expectsOutputToContain($failed->order_id)
            ->expectsOutputToContain($cancelled->order_id)
            ->assertExitCode(0)
            ->run();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_backfill_is_idempotent_after_partial_completion(): void
    {
        $outlet = Outlet::factory()->create();
        $reused = $this->order($outlet, 'Delivered', '100.00', 'partial-reused');
        $this->payment($reused, '25.00', 'completed', 'partial-reused-payment');
        Invoice::create([
            'order_id' => $reused->id,
            'outlet_id' => $reused->outlet_id,
            'invoice_number' => 'INV-existing-'.$reused->id,
            'issue_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'total_amount' => '100.00',
            'paid_amount' => '0.00',
            'balance_amount' => '100.00',
            'status' => Invoice::UNPAID,
        ]);

        $created = $this->order($outlet, 'Paid', '80.00', 'partial-created');
        $this->payment($created, '80.00', 'completed', 'partial-created-payment');

        $blocker = $this->order($outlet, 'New', '10.00', 'partial-blocker');
        $failed = $this->order($outlet, 'Delivered', '60.00', 'partial-failed');
        Invoice::create([
            'order_id' => $blocker->id,
            'outlet_id' => $blocker->outlet_id,
            'invoice_number' => 'INV-'.Carbon::today()->format('Ymd').'-'.$failed->id,
            'issue_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(7)->toDateString(),
            'total_amount' => '10.00',
            'paid_amount' => '0.00',
            'balance_amount' => '10.00',
            'status' => Invoice::UNPAID,
        ]);

        $firstRunExitCode = $this->artisan('invoices:backfill')
            ->expectsOutputToContain($failed->order_id)
            ->expectsOutputToContain('Failed: 1')
            ->assertExitCode(1)
            ->run();

        $invoiceCount = Invoice::count();
        $paymentCount = Payment::count();
        $reusedInvoiceId = $reused->invoice()->value('id');

        $this->assertNotNull($reused->invoice()->value('id'));
        $this->assertNotNull($created->invoice()->value('id'));
        $this->assertSame(1, $firstRunExitCode);

        $this->artisan('invoices:backfill')
            ->expectsOutputToContain($failed->order_id)
            ->expectsOutputToContain('Failed: 1')
            ->assertExitCode(1)
            ->run();

        $this->assertSame($invoiceCount, Invoice::count());
        $this->assertSame($paymentCount, Payment::count());
        $this->assertSame($reusedInvoiceId, $reused->invoice()->value('id'));
        $this->assertSame(1, Invoice::where('order_id', $reused->id)->count());
        $this->assertSame(1, Invoice::where('order_id', $created->id)->count());
    }

    /** @param array<string, mixed> $attributes */
    private function order(Outlet $outlet, string $status, string $total, string $identity, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_id' => 'LEGACY-'.$identity,
            'outlet_id' => $outlet->id,
            'status' => $status,
            'total_amount' => $total,
            'paid_amount' => '999.00',
            'commission_percentage' => '2.00',
            'idempotency_key' => $identity,
        ], $attributes));
    }

    private function payment(Order $order, string $amount, string $status, string $identity): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => $status,
            'idempotency_key' => $identity,
            'receipt_reference' => null,
        ]);
    }
}
