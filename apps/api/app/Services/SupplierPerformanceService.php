<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Carbon\Carbon;

/**
 * Stage producer for the named `supplier` snapshot section.
 *
 * Metrics per supplier (30-day window):
 *  - Fulfillment  (weight 0.50): fulfilled order-item lines ÷ all non-excluded lines
 *      Fulfilled = order item whose order has a completed delivery (delivered).
 *      Eligible lines = all non-excluded order-item lines in the 30-day window.
 *  - On-time      (weight 0.30): on-time records ÷ valid on-time records
 *      Valid on-time record = existing non-excluded order with completed delivery
 *        AND a stored non-null `orders.due_date`. Missing delivery or missing
 *        `due_date` excludes that line only from the on-time denominator.
 *        On-time = `delivery.delivered_at` ≤ `orders.due_date`.
 *  - Catalog quality (weight 0.20): active supplier products ÷ all supplier products.
 *
 * Excluded order statuses: Cancelled, Canceled, Rejected, Invalid.
 * Suppliers with no eligible observations receive `insufficient-data` status and no score.
 */
class SupplierPerformanceService
{
    public const EXCLUDED_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    public const WEIGHT_FULFILLMENT = 0.50;
    public const WEIGHT_ON_TIME = 0.30;
    public const WEIGHT_CATALOG = 0.20;

    /**
     * Return a callback compatible with DataPipelineService::registerStage().
     *
     * The callback receives the window `['start','end','timezone']` and returns
     * an array keyed by `dimension_key` carrying typed supplier payloads.
     */
    public function stageCallback(): callable
    {
        return function (array $window): array {
            return $this->produce($window);
        };
    }

    /**
     * Produce the supplier section payloads for a given window.
     *
     * @return array<string, array{supplier_id: int, ...}>
     */
    public function produce(array $window): array
    {
        $start = Carbon::parse($window['start'])->startOfDay();
        $end = Carbon::parse($window['end'])->endOfDay();

        $suppliers = Supplier::query()->get();

        if ($suppliers->isEmpty()) {
            return [];
        }

        // Pre-fetch catalog counts for all suppliers.
        $catalogByShop = Product::query()
            ->selectRaw('supplier_id, COUNT(*) as total, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active_count')
            ->whereIn('supplier_id', $suppliers->pluck('id'))
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        // Fetch all order items within the 30-day window whose product belongs to a tracked supplier.
        // Eligible: order.status NOT in excluded statuses.
        $items = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('deliveries', 'deliveries.order_id', '=', 'orders.id')
            ->whereIn('products.supplier_id', $suppliers->pluck('id'))
            ->whereNotIn('orders.status', self::EXCLUDED_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->select([
                'order_items.id as item_id',
                'orders.id as order_id',
                'orders.status as order_status',
                'orders.due_date as due_date',
                'deliveries.delivered_at as delivered_at',
                'deliveries.status as delivery_status',
                'products.supplier_id as supplier_id',
            ])
            ->get();

        // Group by supplier.
        $itemsBySupplier = $items->groupBy('supplier_id');

        $result = [];

        foreach ($suppliers as $supplier) {
            $catalogRow = $catalogByShop->get($supplier->id);
            $catalogTotal = (int) ($catalogRow->total ?? 0);
            $catalogActive = (int) ($catalogRow->active_count ?? 0);
            // Product counts are outside the time window — they reflect current catalog state.
            $catalogRatio = $catalogTotal > 0 ? $catalogActive / $catalogTotal : null;

            /** @var \Illuminate\Support\Collection $sItems */
            $sItems = $itemsBySupplier->get($supplier->id, collect());

            $totalLines = $sItems->count();

            // No observations at all: include insufficient-data row only if the supplier exists.
            if ($totalLines === 0 && $catalogTotal === 0) {
                $result["supplier:{$supplier->id}"] = $this->insufficientPayload($supplier, [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'fulfillment' => ['numerator' => 0, 'denominator' => 0, 'ratio' => null],
                    'on_time' => ['numerator' => 0, 'denominator' => 0, 'ratio' => null, 'eligible' => 0, 'excluded' => 0],
                    'catalog' => ['numerator' => 0, 'denominator' => 0, 'ratio' => null],
                    'weights' => ['fulfillment' => self::WEIGHT_FULFILLMENT, 'on_time' => self::WEIGHT_ON_TIME, 'catalog' => self::WEIGHT_CATALOG],
                    'weighted_score' => null,
                ]);
                continue;
            }

            // Fulfillment: fulfilled lines ÷ all non-excluded lines.
            // Fulfilled = completed delivery exists (delivered_at not null).
            $fulfilledCount = $sItems->filter(static function ($row): bool {
                return $row->delivered_at !== null;
            })->count();

            $fulfillmentRatio = $totalLines > 0 ? $fulfilledCount / $totalLines : null;

            // On-time: valid on-time records require BOTH completed delivery AND stored non-null due_date.
            $validOnTime = $sItems->filter(static function ($row): bool {
                return $row->delivered_at !== null && $row->due_date !== null;
            });

            $validCount = $validOnTime->count();
            $excludedOnTime = $totalLines - $validCount;

            $onTimeCount = $validOnTime->filter(function ($row): bool {
                $deliveredAt = Carbon::parse($row->delivered_at);
                $dueDate = Carbon::parse($row->due_date)->endOfDay();
                return $deliveredAt->lte($dueDate);
            })->count();

            $onTimeRatio = $validCount > 0 ? $onTimeCount / $validCount : null;

            // Weighted score: only when all three ratios exist.
            $weightedScore = null;
            if ($fulfillmentRatio !== null && $onTimeRatio !== null && $catalogRatio !== null) {
                $weightedScore = $fulfillmentRatio * self::WEIGHT_FULFILLMENT
                    + $onTimeRatio * self::WEIGHT_ON_TIME
                    + $catalogRatio * self::WEIGHT_CATALOG;
            } elseif ($totalLines === 0) {
                // Window has no order lines — supplier is insufficient-data.
                $result["supplier:{$supplier->id}"] = $this->insufficientPayload($supplier, [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'fulfillment' => [
                        'numerator' => $fulfilledCount,
                        'denominator' => $totalLines,
                        'ratio' => $fulfillmentRatio,
                    ],
                    'on_time' => [
                        'numerator' => $onTimeCount,
                        'denominator' => $validCount,
                        'ratio' => $onTimeRatio,
                        'eligible' => $validCount,
                        'excluded' => $excludedOnTime,
                    ],
                    'catalog' => [
                        'numerator' => $catalogActive,
                        'denominator' => $catalogTotal,
                        'ratio' => $catalogRatio,
                    ],
                    'weights' => ['fulfillment' => self::WEIGHT_FULFILLMENT, 'on_time' => self::WEIGHT_ON_TIME, 'catalog' => self::WEIGHT_CATALOG],
                    'weighted_score' => null,
                ]);
                continue;
            }

            // If any critical observation exists, emit a standard payload.
            $result["supplier:{$supplier->id}"] = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'fulfillment' => [
                    'numerator' => $fulfilledCount,
                    'denominator' => $totalLines,
                    'ratio' => $fulfillmentRatio !== null ? round($fulfillmentRatio, 4) : null,
                ],
                'on_time' => [
                    'numerator' => $onTimeCount,
                    'denominator' => $validCount,
                    'ratio' => $onTimeRatio !== null ? round($onTimeRatio, 4) : null,
                    'eligible' => $validCount,
                    'excluded' => $excludedOnTime,
                ],
                'catalog' => [
                    'numerator' => $catalogActive,
                    'denominator' => $catalogTotal,
                    'ratio' => $catalogRatio !== null ? round($catalogRatio, 4) : null,
                ],
                'weights' => ['fulfillment' => self::WEIGHT_FULFILLMENT, 'on_time' => self::WEIGHT_ON_TIME, 'catalog' => self::WEIGHT_CATALOG],
                'weighted_score' => $weightedScore !== null ? round($weightedScore, 4) : null,
                'status' => $weightedScore !== null ? 'ok' : 'insufficient-data',
            ];
        }

        return $result;
    }

    /**
     * @param Supplier $supplier
     * @param array $overrides
     * @return array
     */
    private function insufficientPayload(Supplier $supplier, array $overrides): array
    {
        return array_merge($overrides, [
            'status' => 'insufficient-data',
        ]);
    }
}
