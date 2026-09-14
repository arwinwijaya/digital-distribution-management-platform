<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;

/**
 * Stage producer for the named `stock` snapshot section.
 *
 * Metrics per product (30-day rolling window):
 *  - total_demand: sum of positive order-item quantities from eligible orders
 *      Eligible = order.status NOT in Cancelled, Canceled, Rejected, Invalid
 *        AND order created within the rolling 30-day window.
 *  - average_daily_demand = total_demand / 30
 *  - lead_time_demand = average_daily_demand × supplier lead_time_days
 *  - reorder_quantity = max(0, lead_time_demand − available_stock), rounded up
 *      available_stock = max(0, products.stock_quantity)
 *      Invalid lead time (null / ≤ 0) → `insufficient-data`, no recommendation.
 *      Zero demand or sufficient stock → no-action (`ok`, quantity 0).
 *
 * This service never executes reorders; it only produces deterministic
 * replenishment recommendations for the admin BI consumer.
 */
class StockPlanningService
{
    public const EXCLUDED_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    public const WINDOW_DAYS = 30;

    /**
     * Return a callback compatible with DataPipelineService::registerStage().
     *
     * The callback receives the window `['start','end','timezone']` and returns
     * an array keyed by `dimension_key` carrying typed stock payloads.
     */
    public function stageCallback(): callable
    {
        return function (array $window): array {
            return $this->produce($window);
        };
    }

    /**
     * Produce the stock section payloads for a given window.
     *
     * @return array<string, array{product_id: int, ...}>
     */
    public function produce(array $window): array
    {
        $start = Carbon::parse($window['start'])->startOfDay();
        $end = Carbon::parse($window['end'])->endOfDay();

        $products = Product::query()->with('supplier')->get();

        if ($products->isEmpty()) {
            return [];
        }

        // Sum only positive quantities on order items whose parent order is
        // eligible (non-excluded status) inside the rolling window.
        $demandByProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $products->pluck('id'))
            ->where('order_items.quantity', '>', 0)
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('order_items.product_id as product_id, COALESCE(SUM(order_items.quantity), 0) as total_demand')
            ->groupBy('order_items.product_id')
            ->get()
            ->keyBy('product_id');

        $result = [];

        foreach ($products as $product) {
            $row = $demandByProduct->get($product->id);
            $totalDemand = (int) ($row->total_demand ?? 0);
            $averageDailyDemand = $totalDemand / self::WINDOW_DAYS;

            $availableStock = self::normalizeStock($product->stock_quantity);
            $leadTimeDays = self::normalizeLeadTime($product->supplier?->lead_time_days);

            if ($leadTimeDays === null) {
                $result["product:{$product->id}"] = $this->insufficientPayload(
                    $product,
                    $totalDemand,
                    $averageDailyDemand,
                    $availableStock
                );
                continue;
            }

            $leadTimeDemand = $averageDailyDemand * $leadTimeDays;
            $reorderQuantity = self::reorderQuantity($averageDailyDemand, $leadTimeDays, $availableStock);

            $result["product:{$product->id}"] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'supplier_id' => $product->supplier_id,
                'lead_time_days' => $leadTimeDays,
                'available_stock' => $availableStock,
                'total_demand' => $totalDemand,
                'average_daily_demand' => round($averageDailyDemand, 4),
                'lead_time_demand' => round($leadTimeDemand, 4),
                'reorder_quantity' => $reorderQuantity,
                'has_warning' => $reorderQuantity > 0,
                'status' => $reorderQuantity > 0 ? 'reorder' : 'ok',
            ];
        }

        return $result;
    }

    /**
     * Exact reorder formula: max(0, average daily demand × lead_time_days − available stock).
     * Fractional results round up to a whole unit; never negative.
     */
    public static function reorderQuantity(float $averageDailyDemand, int $leadTimeDays, int $availableStock): int
    {
        return (int) max(0, (int) ceil($averageDailyDemand * $leadTimeDays - $availableStock));
    }

    /**
     * Negative stock is clamped to zero.
     */
    public static function normalizeStock(mixed $stock): int
    {
        return max(0, (int) ($stock ?? 0));
    }

    /**
     * Lead time must be a positive integer; null/zero/negative is invalid.
     */
    public static function normalizeLeadTime(mixed $leadTimeDays): ?int
    {
        if ($leadTimeDays === null) {
            return null;
        }

        $leadTimeDays = (int) $leadTimeDays;

        return $leadTimeDays > 0 ? $leadTimeDays : null;
    }

    /**
     * @return array{product_id: int, ...}
     */
    private function insufficientPayload(
        Product $product,
        int $totalDemand,
        float $averageDailyDemand,
        int $availableStock,
    ): array {
        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'supplier_id' => $product->supplier_id,
            'lead_time_days' => null,
            'available_stock' => $availableStock,
            'total_demand' => $totalDemand,
            'average_daily_demand' => round($averageDailyDemand, 4),
            'lead_time_demand' => null,
            'reorder_quantity' => 0,
            'has_warning' => false,
            'status' => 'insufficient-data',
        ];
    }
}
