<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Deterministic outlet segments derived from explainable order signals. */
class SegmentationService
{
    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    /** @return array<string, mixed> */
    public function segment(int $outletId): array
    {
        $signals = $this->signals(collect([$outletId]))[$outletId] ?? $this->emptySignals();

        return $this->format($outletId, $signals);
    }

    /** @return array<int, array<string, mixed>> */
    public function segmentAll(): array
    {
        $outlets = Outlet::query()->where('is_active', true)->orderBy('id')->limit(100)->get(['id', 'name']);
        $signals = $this->signals($outlets->pluck('id'));

        return $outlets->map(fn (Outlet $outlet): array => [
            'outlet_id' => (int) $outlet->id,
            'outlet_name' => $outlet->name,
            ...$this->format((int) $outlet->id, $signals[$outlet->id] ?? $this->emptySignals()),
        ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function signals(Collection $outletIds): array
    {
        if ($outletIds->isEmpty()) {
            return [];
        }

        $rows = Order::query()
            ->whereIn('outlet_id', $outletIds->all())
            ->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES)
            ->selectRaw('outlet_id, COUNT(*) as order_count, COALESCE(SUM(CASE WHEN total_amount > 0 THEN total_amount ELSE 0 END), 0) as total_spend, MAX(created_at) as last_order_at')
            ->groupBy('outlet_id')
            ->get()
            ->keyBy('outlet_id');

        $productRows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.outlet_id', $outletIds->all())
            ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
            ->where('order_items.quantity', '>', 0)
            ->selectRaw('orders.outlet_id, COUNT(DISTINCT order_items.product_id) as unique_products')
            ->groupBy('orders.outlet_id')
            ->get()
            ->keyBy('outlet_id');

        $result = [];
        foreach ($outletIds as $outletId) {
            $row = $rows->get($outletId);
            $count = (int) ($row->order_count ?? 0);
            $spend = $this->decimalToCents($row->total_spend ?? 0);
            $lastOrder = $row?->last_order_at ? Carbon::parse($row->last_order_at) : null;
            $result[(int) $outletId] = [
                'order_count' => $count,
                'total_spend' => $this->moneyFromCents($spend),
                'average_order_value' => $this->moneyFromCents($count > 0 ? intdiv($spend, $count) : 0),
                'unique_products' => (int) ($productRows->get($outletId)->unique_products ?? 0),
                'days_since_last_order' => $lastOrder ? $lastOrder->diffInDays(now()) : null,
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $signals */
    private function format(int $outletId, array $signals): array
    {
        $count = (int) $signals['order_count'];
        $spendCents = $this->decimalToCents($signals['total_spend']);
        $days = $signals['days_since_last_order'];
        $segment = match (true) {
            $count === 0 => 'new',
            $days !== null && $days > 90 => 'at_risk',
            $spendCents >= 1000000 && $count >= 5 => 'high_value',
            $count >= 3 && ($days === null || $days <= 30) => 'growing',
            default => 'regular',
        };
        $confidence = $count >= 3 ? 'medium' : 'low';

        return [
            'outlet_id' => $outletId,
            'segment' => $segment,
            'confidence' => $confidence,
            'signals' => $signals,
            'method' => 'rule_based_purchase_signals_v1',
            'method_version' => '1.0.0',
            'measurement' => [
                'measured' => false,
                'acceptance_target' => null,
                'accuracy_target' => null,
                'note' => 'Segment quality requires measured retention or revenue outcomes.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function emptySignals(): array
    {
        return [
            'order_count' => 0,
            'total_spend' => '0.00',
            'average_order_value' => '0.00',
            'unique_products' => 0,
            'days_since_last_order' => null,
        ];
    }

    private function decimalToCents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '0');

        return ((int) ($whole ?: 0) * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function moneyFromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
