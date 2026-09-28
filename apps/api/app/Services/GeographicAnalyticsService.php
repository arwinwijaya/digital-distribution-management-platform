<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Stage producer for the `geographic` snapshot section. Per-outlet rows carry v1 totals plus v2 detail; territory rows are pre-aggregated. Money is integer cents (never float). Invalid coords => `plottable=false`; no territory => Unassigned. */
class GeographicAnalyticsService
{
    private const ELIGIBLE_ORDER_STATUSES = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];
    private const PRODUCT_SUMMARY_LIMIT = 5;

    /** Callback compatible with DataPipelineService::registerStage(). */
    public function stageCallback(): callable
    {
        return fn (array $window): array => $this->produce($window);
    }

    /** Produce the geographic section payloads for a window. */
    public function produce(array $window): array
    {
        $timezone = $window['timezone'] ?? DataPipelineService::TIMEZONE;
        $start = Carbon::parse($window['start'], $timezone)->startOfDay();
        $end = Carbon::parse($window['end'], $timezone)->endOfDay();
        $statuses = self::ELIGIBLE_ORDER_STATUSES;

        $outlets = $this->fetchOutlets();
        if ($outlets->isEmpty()) {
            return [];
        }

        $buckets = $this->accumulateOrders($outlets, $statuses, $start, $end, $timezone);
        $productsByOutlet = $this->fetchProductsByOutlet($outlets, $statuses, $start, $end);

        return $this->mergeWithTerritories(
            $this->buildOutletPayloads($outlets, $buckets, $productsByOutlet, $statuses));
    }

    /** Active outlets with territory names, keyed by id. */
    private function fetchOutlets()
    {
        return Outlet::query()->where('is_active', true)->with('territory')
            ->orderBy('id')->get()->keyBy('id');
    }

    /** Accumulate eligible orders into per-outlet buckets (totals, status maps, sparse daily buckets, latest request). Deterministic order for stable latest-request tie-break. */
    private function accumulateOrders($outlets, array $statuses, $start, $end, string $timezone): array
    {
        $orders = Order::query()
            ->whereIn('status', $statuses)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('outlet_id', $outlets->keys())
            ->orderBy('created_at')->orderBy('id')
            ->get(['id', 'order_id', 'outlet_id', 'status', 'total_amount', 'created_at']);

        $buckets = [];
        foreach ($outlets->keys() as $outletId) {
            $buckets[$outletId] = ['orders' => 0, 'cents' => 0,
                'counts' => array_fill_keys($statuses, 0),
                'status_cents' => array_fill_keys($statuses, 0),
                'days' => [], 'latest' => null];
        }

        foreach ($orders as $order) {
            $status = (string) $order->status;
            $cents = self::decimalToCents($order->total_amount);
            $day = $order->created_at->copy()->setTimezone($timezone)->toDateString();
            $bucket = &$buckets[(int) $order->outlet_id];
            $bucket['orders']++;
            $bucket['cents'] += $cents;
            $bucket['counts'][$status]++;
            $bucket['status_cents'][$status] += $cents;
            if (! isset($bucket['days'][$day])) {
                $bucket['days'][$day] = ['counts' => array_fill_keys($statuses, 0),
                    'cents' => array_fill_keys($statuses, 0)];
            }
            $bucket['days'][$day]['counts'][$status]++;
            $bucket['days'][$day]['cents'][$status] += $cents;
            $createdAt = $order->created_at->copy()->setTimezone($timezone);
            $bucket['latest'] = self::pickLatest($bucket['latest'], [
                'id' => (int) $order->id, 'order_id' => (string) $order->order_id,
                'status' => $status, 'created_at' => $createdAt->toIso8601String(),
            ], $createdAt);
            unset($bucket);
        }

        return $buckets;
    }

    /** Quantity (int) and subtotal (integer cents) per product per outlet. Exact string-to-cents conversion (subtotals ≤ 2 decimals). */
    private function fetchProductsByOutlet($outlets, array $statuses, $start, $end): array
    {
        $itemRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', $statuses)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.outlet_id', $outlets->keys())
            ->select('orders.outlet_id as outlet_id', 'order_items.product_id as product_id', 'order_items.quantity as quantity', 'order_items.subtotal as subtotal')
            ->orderBy('orders.outlet_id')->orderBy('order_items.product_id')
            ->get();

        $productNames = $itemRows->isEmpty() ? collect() : Product::query()
            ->whereIn('id', $itemRows->pluck('product_id')->unique()->all())->pluck('name', 'id');

        $productsByOutlet = [];
        foreach ($itemRows as $itemRow) {
            $outletId = (int) $itemRow->outlet_id;
            $productId = (int) $itemRow->product_id;
            if (! isset($productsByOutlet[$outletId][$productId])) {
                $productsByOutlet[$outletId][$productId] = ['product_id' => $productId,
                    'product_name' => (string) ($productNames[$productId] ?? ''),
                    'quantity' => 0, 'cents' => 0];
            }
            $productsByOutlet[$outletId][$productId]['quantity'] += (int) $itemRow->quantity;
            $productsByOutlet[$outletId][$productId]['cents'] += self::decimalToCents((string) $itemRow->subtotal);
        }

        return $productsByOutlet;
    }

    /** Per-outlet payloads keyed by `outlet:{id}`. */
    private function buildOutletPayloads($outlets, array $buckets, array $productsByOutlet, array $statuses): array
    {
        $payloads = [];
        foreach ($outlets as $outlet) {
            $payloads["outlet:{$outlet->id}"] = $this->buildOutletPayload(
                $outlet, $buckets[$outlet->id], $productsByOutlet[$outlet->id] ?? [], $statuses);
        }

        return $payloads;
    }

    /** Single outlet row: v1 totals plus v2 status/daily/product detail. */
    private function buildOutletPayload($outlet, array $bucket, array $products, array $statuses): array
    {
        $ordersByStatus = [];
        $salesByStatus = [];
        foreach ($statuses as $status) {
            $ordersByStatus[$status] = (int) $bucket['counts'][$status];
            $salesByStatus[$status] = self::formatCents((int) $bucket['status_cents'][$status]);
        }
        [$productSummary, $truncated] = $this->buildProductSummary($products);

        return ['type' => 'outlet', 'outlet_id' => $outlet->id, 'outlet_name' => $outlet->name,
            'territory' => $outlet->territory?->name ?? 'Unassigned',
            'latitude' => $outlet->latitude, 'longitude' => $outlet->longitude,
            'plottable' => self::isValidCoordinate(
                $outlet->latitude === null ? null : (float) $outlet->latitude,
                $outlet->longitude === null ? null : (float) $outlet->longitude),
            'orders' => (int) $bucket['orders'], 'sales' => self::formatCents((int) $bucket['cents']),
            'orders_by_status' => $ordersByStatus, 'sales_by_status' => $salesByStatus,
            'daily_by_status' => $this->buildDailyBuckets($bucket['days'], $statuses),
            'product_summary' => $productSummary, 'product_summary_truncated' => $truncated,
            'latest_request' => $bucket['latest'] === null ? null : [
                'order_id' => $bucket['latest']['order_id'], 'status' => $bucket['latest']['status'],
                'created_at' => $bucket['latest']['created_at']],
        ];
    }

    /** Sparse daily buckets, strictly ascending by YYYY-MM-DD. */
    private function buildDailyBuckets(array $days, array $statuses): array
    {
        ksort($days, SORT_STRING);
        $daily = [];
        foreach ($days as $date => $dayBucket) {
            $counts = [];
            $sales = [];
            foreach ($statuses as $status) {
                $counts[$status] = (int) $dayBucket['counts'][$status];
                $sales[$status] = self::formatCents((int) $dayBucket['cents'][$status]);
            }
            $daily[] = ['date' => $date, 'counts' => $counts, 'sales' => $sales];
        }

        return $daily;
    }

    /** Top-5 products by quantity desc, product_id asc tie-break. */
    private function buildProductSummary(array $products): array
    {
        $products = array_values($products);
        usort($products, static fn (array $a, array $b): int =>
            $b['quantity'] <=> $a['quantity'] ?: $a['product_id'] <=> $b['product_id']);

        return [array_map(static fn (array $product): array => [
            'product_id' => $product['product_id'], 'product_name' => $product['product_name'],
            'quantity' => $product['quantity'], 'subtotal' => self::formatCents($product['cents']),
        ], array_slice($products, 0, self::PRODUCT_SUMMARY_LIMIT)), count($products) > self::PRODUCT_SUMMARY_LIMIT];
    }

    /** Outlet payloads plus per-territory aggregates (integer cents). */
    private function mergeWithTerritories(array $outletPayloads): array
    {
        $aggregates = [];
        foreach ($outletPayloads as $payload) {
            $name = $payload['territory'];
            $aggregates[$name] ??= ['cents' => 0, 'orders' => 0, 'outlets' => 0];
            $aggregates[$name]['cents'] += self::decimalToCents($payload['sales']);
            $aggregates[$name]['orders'] += $payload['orders'];
            $aggregates[$name]['outlets']++;
        }

        $result = $outletPayloads;
        foreach ($aggregates as $name => $agg) {
            $result["territory:{$name}"] = ['type' => 'territory', 'territory' => $name,
                'sales' => self::formatCents($agg['cents']),
                'orders' => $agg['orders'], 'outlets' => $agg['outlets']];
        }

        return $result;
    }

    /** Latest eligible request, deterministically (created_at, then id). */
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

    /** Database decimal/string money to integer cents without float casts. Floats rejected to fail loudly. */
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

    /** Integer cents as a fixed two-decimal string. */
    private static function formatCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $formatted = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    /** Canonical coordinate authority (shared with frontend `isValidPoint()`). Finite int/float only; lat [-90,90], lng [-180,180]; never (0,0). Callers cast DB decimals to float. */
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
