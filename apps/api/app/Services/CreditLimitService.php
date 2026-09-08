<?php

namespace App\Services;

use App\Models\CreditLimit;
use App\Models\Order;
use App\Models\Outlet;
use Illuminate\Validation\ValidationException;

class CreditLimitService
{
    /**
     * Assert that a new order fits within the outlet's configured credit.
     * The caller must hold the outlet lock for the complete order transaction.
     */
    public function assertCanPlace(Outlet $outlet, int $newOrderCents): void
    {
        $limit = CreditLimit::where('outlet_id', $outlet->id)->lockForUpdate()->first();
        // No configured limit preserves the existing T3 behavior (unlimited).
        if (!$limit) {
            return;
        }

        $outstandingCents = 0;
        $orders = Order::where('outlet_id', $outlet->id)
            ->whereIn('status', ['New', 'Confirmed', 'Delivered', 'Partially Paid'])
            ->lockForUpdate()
            ->get(['total_amount', 'paid_amount']);
        foreach ($orders as $order) {
            $outstandingCents += max(0, $this->moneyToCents($order->total_amount) - $this->moneyToCents($order->paid_amount));
        }

        $limitCents = $this->moneyToCents($limit->limit_amount);
        if ($outstandingCents + $newOrderCents > $limitCents) {
            throw ValidationException::withMessages([
                'credit_limit' => 'Order exceeds the outlet available credit.',
            ]);
        }
    }

    public function summary(Outlet $outlet): array
    {
        $limit = CreditLimit::where('outlet_id', $outlet->id)->first();
        $outstandingCents = 0;
        $orders = Order::where('outlet_id', $outlet->id)
            ->whereIn('status', ['New', 'Confirmed', 'Delivered', 'Partially Paid'])
            ->get(['total_amount', 'paid_amount']);
        foreach ($orders as $order) {
            $outstandingCents += max(0, $this->moneyToCents($order->total_amount) - $this->moneyToCents($order->paid_amount));
        }

        $limitCents = $limit ? $this->moneyToCents($limit->limit_amount) : null;
        return [
            'credit_limit' => $limit ? number_format($limitCents / 100, 2, '.', '') : null,
            'outstanding_balance' => number_format($outstandingCents / 100, 2, '.', ''),
            'available_credit' => $limit === null
                ? null
                : number_format(max(0, $limitCents - $outstandingCents) / 100, 2, '.', ''),
        ];
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
