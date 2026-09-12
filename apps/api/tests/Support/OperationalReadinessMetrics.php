<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait OperationalReadinessMetrics
{
    /**
     * @return array{
     *     openInvoice: Invoice,
     *     paidInvoice: Invoice,
     *     ownPayment: Payment,
     *     ownSentReminder: InvoiceReminder,
     *     otherInvoice: Invoice,
     *     otherPayment: Payment
     * }
     */
    protected function seedOperationalMetricsHistory(): array
    {
        [, $openInvoice] = $this->seedInvoice($this->outlet, 'own-open', '2026-09-10', '2026-09-25', 100, 0, Invoice::UNPAID);
        [$paidOrder, $paidInvoice] = $this->seedInvoice($this->outlet, 'own-paid', '2026-09-01', '2026-09-10', 100, 100, Invoice::PAID);
        $ownPayment = $this->seedPayment($paidOrder, 100, 'own-payment', '2026-09-05 10:00:00');
        $ownSentReminder = $this->seedReminder($openInvoice, InvoiceReminder::SENT, 'own-reminder-sent', '2026-09-15');
        $this->seedReminder($openInvoice, InvoiceReminder::FAILED, 'own-reminder-failed', '2026-09-16');

        $otherOutlet = Outlet::factory()->create(['phone' => '+628199999999', 'is_active' => true]);
        [$otherOrder, $otherInvoice] = $this->seedInvoice($otherOutlet, 'other', '2026-09-10', '2026-09-19', 900, 100, Invoice::PARTIALLY_PAID);
        $otherPayment = $this->seedPayment($otherOrder, 100, 'other-payment', '2026-09-12 10:00:00');
        $this->seedReminder($otherInvoice, InvoiceReminder::SENT, 'other-reminder', '2026-09-17');

        return compact('openInvoice', 'paidInvoice', 'ownPayment', 'ownSentReminder', 'otherInvoice', 'otherPayment');
    }

    protected function assertOperationalMetricsForOutlet(string $financeToken): void
    {
        $this->withToken($financeToken)->getJson('/api/finance/metrics?outlet_id='.$this->outlet->id)
            ->assertOk()->assertJsonStructure(['data' => [
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
    }

    protected function assertFinanceHistoriesAreBounded(string $financeToken, array $records): void
    {
        $this->withToken($financeToken)->getJson('/api/invoices?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $records['otherInvoice']->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.has_more', true);
        $this->withToken($financeToken)->getJson('/api/payments?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $records['otherPayment']->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.has_more', true);
        $this->withToken($financeToken)->getJson('/api/finance/reminders?page=1&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $records['ownSentReminder']->id)
            ->assertJsonPath('meta.limit', 1)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.has_more', true);
    }

    protected function assertOutletHistoriesAreScoped(array $records): void
    {
        $invoiceHistory = $this->withToken($this->outletToken)->getJson('/api/invoices?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $paymentHistory = $this->withToken($this->outletToken)->getJson('/api/payments?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 1)->json('data');
        $reminderHistory = $this->withToken($this->outletToken)->getJson('/api/finance/reminders?page=1&limit=100')
            ->assertOk()->assertJsonPath('meta.total', 2)->json('data');

        $this->assertTrue(collect($invoiceHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Invoice history must exclude another outlet.');
        $this->assertTrue(collect($paymentHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Payment history must exclude another outlet.');
        $this->assertTrue(collect($reminderHistory)->every(fn (array $row) => $row['outlet_id'] === $this->outlet->id), 'Reminder history must exclude another outlet.');
        $this->assertSame([$records['paidInvoice']->id, $records['openInvoice']->id], collect($invoiceHistory)->pluck('id')->all(), 'Invoice history ordering must be stable.');
        $this->assertSame([$records['ownPayment']->id], collect($paymentHistory)->pluck('id')->all(), 'Payment history ordering must be stable.');
        $this->assertNotContains($records['otherInvoice']->id, collect($invoiceHistory)->pluck('id')->all());
    }

    protected function assertEmptyOutletMetricsAreZeroSafe(): void
    {
        $emptyUser = $this->operationalUser('outlet', 't10-empty-outlet@example.test');
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
