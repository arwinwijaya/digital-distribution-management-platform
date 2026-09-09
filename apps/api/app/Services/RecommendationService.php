<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Transparent, bounded product recommendations based on an outlet's completed
 * purchase quantities. This intentionally does not use an external model.
 */
class RecommendationService
{
    public const DEFAULT_LIMIT = 10;

    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    /**
     * @return array{recommendations: array<int, array<string, mixed>>, limit: int, data_points: int, fallback: bool, method: string, method_version: string, measurement: array<string, mixed>}
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

        return [
            'recommendations' => $this->format($rows),
            'limit' => $limit,
            'data_points' => $dataPoints,
            'fallback' => $rows->isEmpty(),
            'method' => 'purchase_frequency_v1',
            'method_version' => '1.0.0',
            'measurement' => [
                'measured' => false,
                'acceptance_target' => null,
                'accuracy_target' => null,
                'note' => 'Production acceptance and ranking accuracy require measured usage data.',
            ],
        ];
    }

    private function format(Collection $rows): array
    {
        return $rows->values()->map(function ($row, int $index): array {
            return [
                'rank' => $index + 1,
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'category' => $row->category,
                'price' => number_format((float) $row->price, 2, '.', ''),
                'purchased_quantity' => (int) $row->purchased_quantity,
                'order_count' => (int) $row->order_count,
                'reason' => 'Frequently purchased by this outlet.',
            ];
        })->all();
    }
}
