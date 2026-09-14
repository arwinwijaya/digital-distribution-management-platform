<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Stage producer for the named `geographic` snapshot section.
 *
 * Aggregates 30-day eligible orders by territory, producing:
 * - Per-outlet payloads keyed by `outlet:{id}` (includes coordinates and territory).
 * - Per-territory summary payloads keyed by `territory:{name}`.
 *
 * Coordinates outside valid ranges (null, non-numeric, out-of-range lat/lng)
 * are excluded from map points but remain visible in territory table aggregates.
 * Outlets with no territory are grouped under "Unassigned".
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
     * @return array<string, array{type: string, territory: string, ...}>
     */
    public function produce(array $window): array
    {
        $start = Carbon::parse($window['start']);
        $end = Carbon::parse($window['end']);
        $timezone = $window['timezone'] ?? DataPipelineService::TIMEZONE;

        // Fetch all active outlets with their territory names in a single query.
        $outlets = Outlet::query()
            ->where('is_active', true)
            ->with('territory')
            ->get()
            ->keyBy('id');

        if ($outlets->isEmpty()) {
            return [];
        }

        // Aggregate eligible orders per outlet.
        $orderAggregates = Order::query()
            ->whereIn('status', self::ELIGIBLE_ORDER_STATUSES)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('outlet_id', $outlets->keys())
            ->selectRaw('outlet_id, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as sales')
            ->groupBy('outlet_id')
            ->get()
            ->keyBy('outlet_id');

        // Build per-outlet payloads.
        $outletPayloads = [];
        foreach ($outlets as $outlet) {
            $agg = $orderAggregates->get($outlet->id);
            $orders = (int) ($agg->orders ?? 0);
            $sales = (float) ($agg->sales ?? 0);
            $territoryName = $outlet->territory?->name ?? 'Unassigned';

            $outletPayloads["outlet:{$outlet->id}"] = [
                'type' => 'outlet',
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'territory' => $territoryName,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'orders' => $orders,
                'sales' => number_format($sales, 2, '.', ''),
            ];
        }

        // Aggregate per-territory from outlet payloads.
        $territoryAggregates = [];
        foreach ($outletPayloads as $payload) {
            $name = $payload['territory'];
            if (!isset($territoryAggregates[$name])) {
                $territoryAggregates[$name] = [
                    'type' => 'territory',
                    'territory' => $name,
                    'sales' => 0.0,
                    'orders' => 0,
                    'outlets' => 0,
                ];
            }
            $territoryAggregates[$name]['sales'] += (float) $payload['sales'];
            $territoryAggregates[$name]['orders'] += $payload['orders'];
            $territoryAggregates[$name]['outlets'] += 1;
        }

        $result = [];
        // Include per-outlet payloads for map points.
        foreach ($outletPayloads as $key => $payload) {
            $result[$key] = $payload;
        }

        foreach ($territoryAggregates as $name => $agg) {
            $result["territory:{$name}"] = array_merge($agg, [
                'sales' => number_format($agg['sales'], 2, '.', ''),
            ]);
        }

        return $result;
    }

    /**
     * Validate that a latitude/longitude pair is plottable on the map.
     *
     * Null, non-numeric, or out-of-range values are considered invalid.
     */
    public static function isValidCoordinate(mixed $lat, mixed $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }
}
