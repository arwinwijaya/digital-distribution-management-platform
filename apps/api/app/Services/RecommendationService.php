<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transparent, bounded product recommendations based on an outlet's completed
 * purchase quantities. This intentionally does not use an external model.
 */
class RecommendationService
{
    public const DEFAULT_LIMIT = 10;

    public const MIN_DATA_POINTS = 3;

    public const WINDOW_DAYS = 30;

    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    /**
     * @return array{recommendations: array<int, array<string, mixed>>, limit: int, data_points: int, data_sufficiency: array<string, mixed>, fallback: bool, method: string, method_version: string, measurement: array<string, mixed>}
     */
    public function recommend(?int $outletId, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::DEFAULT_LIMIT));
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
            ->where('order_items.quantity', '>', 0)
            ->where('products.is_active', true)
            ->where(function ($supplier) {
                $supplier->whereNull('products.supplier_id')
                    ->orWhere('suppliers.subscription_status', 'active');
            })
            ->where('products.stock_quantity', '>', 0);

        if ($outletId !== null) {
            $query->where('orders.outlet_id', $outletId);
        }

        $rows = $query
            ->selectRaw('products.id as product_id, products.name, products.sku, products.category, products.price, SUM(order_items.quantity) as purchased_quantity, COUNT(DISTINCT orders.id) as order_count')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.category', 'products.price')
            ->orderByDesc('purchased_quantity')
            ->orderByDesc('order_count')
            ->orderBy('products.id')
            ->limit($limit)
            ->get();

        $dataPointsQuery = Order::query()->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES);
        if ($outletId !== null) {
            $dataPointsQuery->where('outlet_id', $outletId);
        }
        $dataPoints = (int) $dataPointsQuery->whereHas('items', fn ($items) => $items->where('quantity', '>', 0))->count();

        $daysInWindow = $this->daysInWindow($outletId);
        $hasObservations = $daysInWindow > 0;
        $limitedData = $hasObservations && $daysInWindow < self::WINDOW_DAYS;

        return [
            'recommendations' => $this->format($rows, $outletId),
            'limit' => $limit,
            'data_points' => $dataPoints,
            'data_sufficiency' => [
                'level' => $dataPoints === 0 ? 'insufficient' : ($dataPoints >= self::MIN_DATA_POINTS ? 'adequate' : 'limited'),
                // 30-day sparse-data contract: expose the rolling window and whether the
                // recent-average fallback is in effect. All 30+ days would be adequate.
                'window_days' => self::WINDOW_DAYS,
                'days_in_window' => $daysInWindow,
                'recent_average_fallback' => $limitedData,
                'sparse' => $limitedData,
                'minimum_recommended' => self::MIN_DATA_POINTS,
                'note' => $dataPoints === 0
                    ? 'No eligible order history; empty output with insufficient-data metadata.'
                    : ($limitedData
                        ? 'Recent-average heuristic fallback over fewer than 30 days; limited-data, low-confidence ranking quality, not a probability.'
                        : 'Non-probabilistic heuristic based on completed order count; ranking quality is not a probability.'),
            ],
            'fallback' => $rows->isEmpty(),
            'method' => 'purchase_frequency_v1',
            'method_version' => '1.0.0',
            'measurement' => [
                'measured' => false,
                'acceptance_target' => null,
                'accuracy_target' => null,
                'note' => 'Production acceptance and ranking accuracy require measured usage data.',
            ],
            'low_confidence' => $limitedData,
        ];
    }

    /**
     * Count distinct qualifying order days within the rolling 30-day window.
     */
    private function daysInWindow(?int $outletId): int
    {
        $windowStart = now()->copy()->subDays(self::WINDOW_DAYS - 1);

        return (int) Order::query()
            ->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES)
            ->when($outletId !== null, fn ($query) => $query->where('outlet_id', $outletId))
            ->whereHas('items', fn ($items) => $items->where('quantity', '>', 0))
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('COUNT(DISTINCT DATE(created_at))')
            ->value(DB::raw('COUNT(DISTINCT DATE(created_at))'));
    }

    private function format(Collection $rows, ?int $outletId): array
    {
        $reason = $outletId === null
            ? 'Frequently purchased across all outlets.'
            : 'Frequently purchased by this outlet.';

        return $rows->values()->map(function ($row, int $index) use ($reason): array {
            return [
                'rank' => $index + 1,
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'category' => $row->category,
                'price' => number_format((float) $row->price, 2, '.', ''),
                'purchased_quantity' => (int) $row->purchased_quantity,
                'order_count' => (int) $row->order_count,
                'reason' => $reason,
            ];
        })->all();
    }
}
