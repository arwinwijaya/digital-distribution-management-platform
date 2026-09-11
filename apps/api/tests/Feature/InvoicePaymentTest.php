<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InvoicePaymentFixture;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;
    use InvoicePaymentFixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->initInvoicePaymentFixture(
            'invoice-finance@example.test',
            'invoice-payment-outlet@example.test',
            'invoice-payment-admin@example.test',
        );
    }

    public function test_finance_can_record_delivered_payment(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();

        $response = $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 300000,
            'payment_method' => 'cash',
            'idempotency_key' => 'finance-delivered-payment',
        ]);

        $response->assertCreated()->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 300000,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'paid_amount' => 300000,
            'balance_amount' => 700000,
            'status' => Invoice::PARTIALLY_PAID,
        ]);
    }

    public function test_partially_paid_order_accepts_remaining_payment(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 300000,
            'payment_method' => 'cash',
            'idempotency_key' => 'partially-paid-first',
        ])->assertCreated();

        $this->assertSame('Partially Paid', $order->fresh()->status);
        $response = $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 700000,
            'payment_method' => 'cash',
            'idempotency_key' => 'partially-paid-remaining',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => Invoice::PAID,
            'balance_amount' => 0,
        ]);
    }

    public function test_partial_payment_updates_invoice(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $dueDate = $invoice->due_date->toDateString();

        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 300000,
            'payment_method' => 'cash',
            'idempotency_key' => 'partial-invoice-payment',
        ])->assertCreated();

        $invoice = $invoice->fresh();
        $this->assertSame(Invoice::PARTIALLY_PAID, $invoice->status);
        $this->assertSame('300000.00', (string) $invoice->paid_amount);
        $this->assertSame('700000.00', (string) $invoice->balance_amount);
        $this->assertSame($dueDate, $invoice->due_date->toDateString());
    }

    public function test_full_payment_closes_invoice(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 1000000,
            'payment_method' => 'cash',
            'idempotency_key' => 'full-invoice-payment',
        ])->assertCreated();

        $invoice = $invoice->fresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('0.00', (string) $invoice->balance_amount);
        $this->assertSame('1000000.00', (string) $invoice->paid_amount);
        $this->assertSame(0, Invoice::whereKey($invoice->id)
            ->whereIn('status', [Invoice::UNPAID, Invoice::PARTIALLY_PAID])
            ->count());
    }

    public function test_invalid_payment_values_do_not_mutate(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $beforeOrder = $order->fresh()->only(['status', 'paid_amount']);
        $beforeInvoice = $invoice->fresh()->only(['status', 'paid_amount', 'balance_amount']);

        foreach ([0, -1] as $index => $amount) {
            $this->withToken($this->financeToken)->postJson('/api/payments', [
                'order_id' => $order->id,
                'amount' => $amount,
                'payment_method' => 'cash',
                'idempotency_key' => 'invalid-value-'.$index,
            ])->assertStatus(422)->assertJsonValidationErrors(['amount']);
        }
        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 1000001,
            'payment_method' => 'cash',
            'idempotency_key' => 'over-invoice',
        ])->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($beforeOrder, $order->fresh()->only(['status', 'paid_amount']));
        $this->assertSame($beforeInvoice, $invoice->fresh()->only(['status', 'paid_amount', 'balance_amount']));
    }

    public function test_same_identity_replays_without_mutation(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $payload = [
            'order_id' => $order->id,
            'amount' => 300000,
            'payment_method' => 'cash',
            'idempotency_key' => 'invoice-replay',
        ];
        $first = $this->withToken($this->financeToken)->postJson('/api/payments', $payload);
        $first->assertCreated();
        $beforeInvoice = $invoice->fresh()->only(['status', 'paid_amount', 'balance_amount']);
        $beforeHistory = $order->statusHistory()->count();

        $replay = $this->withToken($this->financeToken)->postJson('/api/payments', $payload);
        $replay->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($beforeInvoice, $invoice->fresh()->only(['status', 'paid_amount', 'balance_amount']));
        $this->assertSame($beforeHistory, $order->statusHistory()->count());
    }

    public function test_conflicting_identity_is_rejected(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $payload = [
            'order_id' => $order->id,
            'amount' => 300000,
            'payment_method' => 'cash',
            'idempotency_key' => 'invoice-conflict',
        ];
        $this->withToken($this->financeToken)->postJson('/api/payments', $payload)->assertCreated();
        $other = $this->deliveredInvoice(500000)[0];

        $this->withToken($this->financeToken)->postJson('/api/payments', [
            ...$payload,
            'order_id' => $other->id,
            'amount' => 100000,
        ])->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('700000.00', (string) $invoice->fresh()->balance_amount);
        $this->assertSame('500000.00', (string) $other->fresh()->total_amount);
        $this->assertSame('0.00', (string) Invoice::where('order_id', $other->id)->value('paid_amount'));
    }

    public function test_paid_invoice_rejects_payment(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $invoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $invoice->total_amount,
            'balance_amount' => 0,
        ]);
        $before = Payment::count();

        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 1,
            'payment_method' => 'cash',
            'idempotency_key' => 'paid-invoice-rejected',
        ])->assertStatus(422)->assertJsonValidationErrors(['order_id']);

        $this->assertSame($before, Payment::count());
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
    }

    public function test_cancelled_invoice_rejects_payment(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        $invoice->update([
            'status' => Invoice::CANCELLED,
            'balance_amount' => 0,
        ]);
        $before = Payment::count();

        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 1,
            'payment_method' => 'cash',
            'idempotency_key' => 'cancelled-invoice-rejected',
        ])->assertStatus(422)->assertJsonValidationErrors(['order_id']);

        $this->assertSame($before, Payment::count());
        $this->assertSame(Invoice::CANCELLED, $invoice->fresh()->status);
    }

    public function test_only_completed_positive_payments_affect_balance(): void
    {
        [$order, $invoice] = $this->deliveredInvoice();
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 200000,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'valid-existing',
        ]);
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 0,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'zero-existing',
        ]);
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => -50000,
            'payment_method' => 'cash', 'status' => 'completed', 'idempotency_key' => 'negative-existing',
        ]);
        Payment::create([
            'order_id' => $order->id, 'outlet_id' => $order->outlet_id, 'amount' => 600000,
            'payment_method' => 'cash', 'status' => 'pending', 'idempotency_key' => 'pending-existing',
        ]);

        $this->withToken($this->financeToken)->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'idempotency_key' => 'valid-new',
        ])->assertCreated();

        $invoice = $invoice->fresh();
        $this->assertSame('300000.00', (string) $invoice->paid_amount);
        $this->assertSame('700000.00', (string) $invoice->balance_amount);
        $this->assertSame(Invoice::PARTIALLY_PAID, $invoice->status);
    }

}
