<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Stage producer for the named `geographic` snapshot section.
 *
 * Aggregates 30-day eligible orders by territory, producing:
 * - Per-outlet payloads keyed by `outlet:{id}` (includes coordinates and territory).
 * - Per-territory summary payloads keyed by `territory:{name}`.
 *
 * Coordinates outside valid ranges (null, non-numeric, out-of-range lat/lng)
 * are reported with `plottable=false` (never dropped from the payload) but
 * remain visible in territory table aggregates. Outlets with no territory are
 * grouped under "Unassigned".
 */
class GeographicAnalyticsService
{
    private const ELIGIBLE_ORDER_STATUSES = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];

    /**
     * Return a callback compatible with DataPipelineService::registerStage().
     *
     * The callback receives the window `['start','end','timezone']` and returns
     * an array keyed by `dimension_key` carrying typed payloads.
     */
    public function stageCallback(): callable
    {
        return function (array $window): array {
            return $this->produce($window);
        };
    }

    /**
     * Produce the geographic section payloads for a given window.
     *
     * Each outlet row carries the v1 totals (`orders`, `sales`) plus the v2
     * detail used by the Outlet Request Map Dashboard: `plottable`,
     * `orders_by_status`, `sales_by_status`, `daily_by_status`,
     * `product_summary`, `product_summary_truncated`, and `latest_request`.
     * Territory rows are pre-aggregated here and remain unchanged in shape.
     *
     * Monetary aggregation is integer-cent safe: database decimal strings are
     * converted to cents before summing and never cast to float.
     *
     * @return array<string, array{type: string, territory: string, ...}>
     */
    public function produce(array $window): array
    {
        $timezone = $window['timezone'] ?? DataPipelineService::TIMEZONE;
        $start = Carbon::parse($window['start'], $timezone)->startOfDay();
        $end = Carbon::parse($window['end'], $timezone)->endOfDay();

        // Fetch all active outlets with their territory names in a single query.
        $outlets = Outlet::query()
            ->where('is_active', true)
            ->with('territory')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($outlets->isEmpty()) {
            return [];
        }

        $statuses = self::ELIGIBLE_ORDER_STATUSES;

        // Eligible orders for the window, deterministically ordered so the
        // latest-request tie-break is stable across runs.
        $orders = Order::query()
            ->whereIn('status', $statuses)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('outlet_id', $outlets->keys())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'order_id', 'outlet_id', 'status', 'total_amount', 'created_at']);

        // Per-outlet accumulation buckets, keyed by outlet id.
        $aggregates = [];
        foreach ($outlets->keys() as $outletId) {
            $aggregates[$outletId] = [
                'orders' => 0,
                'cents' => 0,
                'counts' => array_fill_keys($statuses, 0),
                'status_cents' => array_fill_keys($statuses, 0),
                'days' => [],
                'latest' => null,
            ];
        }

        foreach ($orders as $order) {
            $outletId = (int) $order->outlet_id;
            $status = (string) $order->status;
            $cents = self::decimalToCents($order->total_amount);
            $day = $order->created_at->copy()->setTimezone($timezone)->toDateString();

            $bucket = &$aggregates[$outletId];
            $bucket['orders']++;
            $bucket['cents'] += $cents;
            $bucket['counts'][$status]++;
            $bucket['status_cents'][$status] += $cents;

            if (! isset($bucket['days'][$day])) {
                $bucket['days'][$day] = [
                    'counts' => array_fill_keys($statuses, 0),
                    'cents' => array_fill_keys($statuses, 0),
                ];
            }
            $bucket['days'][$day]['counts'][$status]++;
            $bucket['days'][$day]['cents'][$status] += $cents;

            $createdAt = $order->created_at->copy()->setTimezone($timezone);
            $bucket['latest'] = self::pickLatest($bucket['latest'], [
                'id' => (int) $order->id,
                'order_id' => (string) $order->order_id,
                'status' => $status,
                'created_at' => $createdAt->toIso8601String(),
            ], $createdAt);
            unset($bucket);
        }

        // Product summary: accumulate quantity (int) and subtotal (integer
        // cents) per product in PHP so no monetary value is ever summed as
        // a float. Each stored subtotal has at most two decimals, so the
        // string-to-cents conversion below is exact.
        $itemRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', $statuses)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.outlet_id', $outlets->keys())
            ->select('orders.outlet_id as outlet_id', 'order_items.product_id as product_id', 'order_items.quantity as quantity', 'order_items.subtotal as subtotal')
            ->orderBy('orders.outlet_id')
            ->orderBy('order_items.product_id')
            ->get();

        $productNames = $itemRows->isEmpty()
            ? collect()
            : Product::query()
                ->whereIn('id', $itemRows->pluck('product_id')->unique()->all())
                ->pluck('name', 'id');

        $productsByOutlet = [];
        foreach ($itemRows as $itemRow) {
            $outletId = (int) $itemRow->outlet_id;
            $productId = (int) $itemRow->product_id;
            if (! isset($productsByOutlet[$outletId][$productId])) {
                $productsByOutlet[$outletId][$productId] = [
                    'product_id' => $productId,
                    'product_name' => (string) ($productNames[$productId] ?? ''),
                    'quantity' => 0,
                    'cents' => 0,
                ];
            }
            $productsByOutlet[$outletId][$productId]['quantity'] += (int) $itemRow->quantity;
            $productsByOutlet[$outletId][$productId]['cents'] += self::decimalToCents((string) $itemRow->subtotal);
        }

        // Build per-outlet payloads.
        $outletPayloads = [];
        foreach ($outlets as $outlet) {
            $bucket = $aggregates[$outlet->id];
            $territoryName = $outlet->territory?->name ?? 'Unassigned';

            $ordersByStatus = [];
            $salesByStatus = [];
            foreach ($statuses as $status) {
                $ordersByStatus[$status] = (int) $bucket['counts'][$status];
                $salesByStatus[$status] = self::formatCents((int) $bucket['status_cents'][$status]);
            }

            $days = $bucket['days'];
            ksort($days, SORT_STRING);
            $dailyByStatus = [];
            foreach ($days as $date => $dayBucket) {
                $dayCounts = [];
                $daySales = [];
                foreach ($statuses as $status) {
                    $dayCounts[$status] = (int) $dayBucket['counts'][$status];
                    $daySales[$status] = self::formatCents((int) $dayBucket['cents'][$status]);
                }
                $dailyByStatus[] = [
                    'date' => $date,
                    'counts' => $dayCounts,
                    'sales' => $daySales,
                ];
            }

            $products = array_values($productsByOutlet[$outlet->id] ?? []);
            usort($products, static function (array $a, array $b): int {
                return $b['quantity'] <=> $a['quantity']
                    ?: $a['product_id'] <=> $b['product_id'];
            });
            $productSummaryTruncated = count($products) > 5;
            $productSummary = array_map(static function (array $product): array {
                return [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product_name'],
                    'quantity' => $product['quantity'],
                    'subtotal' => self::formatCents($product['cents']),
                ];
            }, array_slice($products, 0, 5));

            $outletPayloads["outlet:{$outlet->id}"] = [
                'type' => 'outlet',
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'territory' => $territoryName,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'plottable' => self::isValidCoordinate(
                    $outlet->latitude === null ? null : (float) $outlet->latitude,
                    $outlet->longitude === null ? null : (float) $outlet->longitude,
                ),
                'orders' => (int) $bucket['orders'],
                'sales' => self::formatCents((int) $bucket['cents']),
                'orders_by_status' => $ordersByStatus,
                'sales_by_status' => $salesByStatus,
                'daily_by_status' => $dailyByStatus,
                'product_summary' => $productSummary,
                'product_summary_truncated' => $productSummaryTruncated,
                'latest_request' => $bucket['latest'] === null ? null : [
                    'order_id' => $bucket['latest']['order_id'],
                    'status' => $bucket['latest']['status'],
                    'created_at' => $bucket['latest']['created_at'],
                ],
            ];
        }

        // Aggregate per-territory from outlet payloads using integer cents.
        $territoryAggregates = [];
        foreach ($outletPayloads as $payload) {
            $name = $payload['territory'];
            if (!isset($territoryAggregates[$name])) {
                $territoryAggregates[$name] = [
                    'type' => 'territory',
                    'territory' => $name,
                    'cents' => 0,
                    'orders' => 0,
                    'outlets' => 0,
                ];
            }
            $territoryAggregates[$name]['cents'] += self::decimalToCents($payload['sales']);
            $territoryAggregates[$name]['orders'] += $payload['orders'];
            $territoryAggregates[$name]['outlets'] += 1;
        }

        $result = [];
        // Include per-outlet payloads for map points.
        foreach ($outletPayloads as $key => $payload) {
            $result[$key] = $payload;
        }

        foreach ($territoryAggregates as $name => $agg) {
            $result["territory:{$name}"] = [
                'type' => 'territory',
                'territory' => $name,
                'sales' => self::formatCents($agg['cents']),
                'orders' => $agg['orders'],
                'outlets' => $agg['outlets'],
            ];
        }

        return $result;
    }

    /**
     * Pick the latest eligible request deterministically (created_at, then id).
     *
     * @param array{id: int, order_id: string, status: string, created_at: string}|null $current
     * @param array{id: int, order_id: string, status: string, created_at: string} $candidate
     * @return array{id: int, order_id: string, status: string, created_at: string}
     */
    private static function pickLatest(?array $current, array $candidate, CarbonInterface $candidateCreatedAt): array
    {
        if ($current === null) {
            return $candidate;
        }

        $currentCreatedAt = Carbon::parse($current['created_at']);
        if ($candidateCreatedAt->greaterThan($currentCreatedAt)) {
            return $candidate;
        }
        if ($candidateCreatedAt->equalTo($currentCreatedAt) && $candidate['id'] > $current['id']) {
            return $candidate;
        }

        return $current;
    }

    /**
     * Convert a database decimal/string monetary value to integer cents without
     * ever casting to float. Floats are deliberately not accepted so a float
     * monetary value fails loudly instead of being summed imprecisely.
     */
    private static function decimalToCents(string|int|null $value): int
    {
        if ($value === null) {
            return 0;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return 0;
        }

        $negative = str_starts_with($string, '-');
        if ($negative || str_starts_with($string, '+')) {
            $string = substr($string, 1);
        }

        $parts = explode('.', $string, 2);
        $whole = $parts[0] === '' ? '0' : $parts[0];
        $fraction = str_pad($parts[1] ?? '', 2, '0');
        $fraction = substr($fraction, 0, 2);

        $cents = ((int) $whole) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    /**
     * Format integer cents as a fixed two-decimal string.
     */
    private static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $formatted = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    /**
     * Validate that a latitude/longitude pair is plottable on the map.
     *
     * This is the canonical coordinate authority shared with the frontend
     * `isValidPoint()`. It deliberately performs no casting: values must be
     * finite `int`/`float` numbers (numeric strings are rejected), within
     * latitude [-90, 90] and longitude [-180, 180], and not exactly (0, 0).
     * Callers holding database decimal strings must cast to `float` at this
     * boundary before calling.
     */
    public static function isValidCoordinate(mixed $lat, mixed $lng): bool
    {
        if (!is_int($lat) && !is_float($lat)) {
            return false;
        }
        if (!is_int($lng) && !is_float($lng)) {
            return false;
        }
        if (!is_finite((float) $lat) || !is_finite((float) $lng)) {
            return false;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }
        if ((float) $lat === 0.0 && (float) $lng === 0.0) {
            return false;
        }

        return true;
    }
}
