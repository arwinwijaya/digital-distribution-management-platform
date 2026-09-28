<?php

namespace App\Support;

use App\Models\Order;
use App\Services\InvoiceService;

/**
 * Format orders for API responses. Keeps controller methods lean.
 */
class OrderFormatter
{
    public function format(Order $order, bool $includeHistory = false): array
    {
        $data = [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'outlet_id' => $order->outlet_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'paid_amount' => $order->paid_amount,
            'outstanding_balance' => number_format(
                max(0, ((float) $order->total_amount) - ((float) $order->paid_amount)),
                2,
                '.',
                ''
            ),
            'promotion_id' => $order->promotion_id,
            'discount_amount' => $order->discount_amount,
            'commission_percentage' => $order->commission_percentage,
            'items' => $order->items->map(fn ($item) => $this->formatItem($item)),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        if ($includeHistory) {
            $data['status_history'] = $order->statusHistory->map(fn ($history) => [
                'status' => $history->status,
                'notes' => $history->notes,
                'created_at' => $history->created_at,
            ]);
        }

        if ($order->relationLoaded('invoice') && $order->invoice) {
            $data['invoice'] = app(InvoiceService::class)->format($order->invoice);
        }

        return $data;
    }

    private function formatItem($item): array
    {
        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product->name ?? null,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'subtotal' => $item->subtotal,
        ];
    }
}