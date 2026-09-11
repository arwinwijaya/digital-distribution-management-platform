<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InvoiceService
{
    public const DEFAULT_TERM_DAYS = 7;

    /**
     * Create or reuse the invoice for an order. The caller must hold the order
     * row lock; the invoice lock and unique order_id constraint make retries
     * safe even when an invoice already exists.
     */
    public function createForApprovedOrder(Order $order): Invoice
    {
        $existing = Invoice::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $order->loadMissing('outlet');
        $issueDate = Carbon::today();
        $termDays = $order->outlet?->effectivePaymentTermDays() ?? self::DEFAULT_TERM_DAYS;
        $total = $order->total_amount;

        return Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-'.$issueDate->format('Ymd').'-'.$order->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $issueDate->copy()->addDays($termDays)->toDateString(),
            'total_amount' => $total,
            'paid_amount' => 0,
            'balance_amount' => $total,
            'status' => Invoice::UNPAID,
        ]);
    }

    /**
     * Cancel an unpaid invoice and its order atomically. Any payment row,
     * regardless of status or amount, permanently blocks cancellation.
     */
    public function cancelOrder(int $orderId): Invoice
    {
        return DB::transaction(function () use ($orderId): Invoice {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $invoice = Invoice::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->payments()->exists()) {
                throw new ConflictHttpException('Orders with payment rows cannot be cancelled.');
            }
            if (! in_array($invoice->status, [Invoice::UNPAID, Invoice::PARTIALLY_PAID], true)) {
                throw new ConflictHttpException('Only unpaid invoices can be cancelled.');
            }
            if ($order->status === 'Cancelled' || $order->status === 'Paid') {
                throw new ConflictHttpException('The order cannot be cancelled in its current state.');
            }

            $invoice->update([
                'status' => Invoice::CANCELLED,
                'balance_amount' => 0,
            ]);
            $order->recordStatus('Cancelled', 'Invoice cancelled by admin');

            return $invoice->fresh();
        });
    }

    /** @return array<string, mixed> */
    public function format(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'outlet_id' => $invoice->outlet_id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'paid_amount' => $invoice->paid_amount,
            'balance_amount' => $invoice->balance_amount,
            'status' => $invoice->status,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ];
    }
}
