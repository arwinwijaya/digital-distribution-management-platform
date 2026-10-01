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
     *
     * New orders have no invoice yet — they are cancelled by transitioning
     * the order status directly (no invoice row). The caller may pass a
     * cancelled order as a placeholder invoice variant is never used.
     */
    public function cancelOrder(int $orderId): Invoice
    {
        return DB::transaction(function () use ($orderId): Invoice {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            // New orders have no invoice row yet. Transition order directly.
            $invoice = Invoice::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            if ($invoice === null) {
                if ($order->status !== 'New') {
                    throw new ConflictHttpException("The order cannot be cancelled in its current state.");
                }
                if ($order->payments()->exists()) {
                    throw new ConflictHttpException('Orders with payment rows cannot be cancelled.');
                }
                $order->recordStatus('Cancelled', 'Order cancelled before invoice creation');

                // Return a transient Invoice-like placeholder that satisfies the
                // Invoice return type so callers can still format a response.
                // Using a synthetic Invoice instance without persisting it keeps
                // the OrderController contract stable and avoids changing return
                // type across the call boundary.
                $placeholder = new Invoice([
                    'order_id'     => $order->id,
                    'outlet_id'    => $order->outlet_id,
                    'status'       => Invoice::CANCELLED,
                    'total_amount' => $order->total_amount,
                    'balance_amount' => 0,
                ]);
                $placeholder->id = 0;
                $placeholder->exists = false;

                return $placeholder;
            }

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

    /** @return array<string, mixed> */
    public function getDetail(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'order.items.product',
            'outlet',
        ]);

        // Load all payments for the order without filtering by status, ordered newest first
        $payments = $invoice->order
            ? $invoice->order->payments()->orderByDesc('created_at')->orderByDesc('id')->get()
            : collect();

        $lineItems = $invoice->order
            ? $invoice->order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    // Frozen snapshot with fallback to live product name per spec
                    'product_name' => $item->product_name_snapshot ?? $item->product?->name ?? null,
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            })->values()->all()
            : [];

        $paymentsData = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'order_id' => $payment->order_id,
                'outlet_id' => $payment->outlet_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ];
        })->values()->all();

        $outletData = $invoice->outlet ? [
            'id' => $invoice->outlet->id,
            'name' => $invoice->outlet->name,
            'phone' => $invoice->outlet->phone,
            'address' => $invoice->outlet->address,
            'city' => $invoice->outlet->city,
        ] : null;

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
            'is_overdue' => $this->isOverdue($invoice),
            'overdue' => $this->isOverdue($invoice),
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
            'outlet' => $outletData,
            'line_items' => $lineItems,
            'items' => $lineItems,
            'payments' => $paymentsData,
            'payment_history' => $paymentsData,
        ];
    }

    private function isOverdue(Invoice $invoice): bool
    {
        if ($invoice->due_date === null) {
            return false;
        }
        $balance = (float) $invoice->balance_amount;
        if ($balance <= 0) {
            return false;
        }
        return $invoice->due_date->isPast() && $invoice->due_date->toDateString() < Carbon::today()->toDateString();
    }
}
