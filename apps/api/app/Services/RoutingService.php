<?php

namespace App\Services;

use App\Models\Delivery;

class RoutingService
{
    /**
     * Hard cap on the number of stops a single deterministic plan will return.
     * Keeps the bounded nearest-neighbor pass O(n^2) with a small, predictable n
     * and prevents an unbounded payload when a driver accumulates many orders.
     */
    public const MAX_STOPS = 20;

    /**
     * Deterministic, bounded nearest-neighbor route plan.
     *
     * Candidates are the driver's active deliveries (assigned/in_progress) whose
     * order outlet carries coordinates. The first candidate by id acts as the
     * origin; every subsequent stop is chosen as the nearest unvisited one,
     * ties broken by ascending id so the output is stable across runs.
     *
     * Returns `[]` when no candidate has usable coordinates.
     *
     * @return array{stops: list<array{id:int,latitude:float,longitude:float,distance_km:float}>, total_distance_km: float}|array{}
     */
    public function plan(Delivery $delivery): array
    {
        $candidates = Delivery::query()
            ->with('order.outlet')
            ->where('driver_id', $delivery->driver_id)
            ->whereIn('status', [Delivery::ASSIGNED, Delivery::IN_PROGRESS])
            ->orderBy('id')
            ->get()
            ->map(function (Delivery $candidate): ?array {
                $outlet = $candidate->order?->outlet;
                if (!$outlet || $outlet->latitude === null || $outlet->longitude === null) {
                    return null;
                }

                return [
                    'id' => $candidate->id,
                    'latitude' => (float) $outlet->latitude,
                    'longitude' => (float) $outlet->longitude,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($candidates === []) {
            return [];
        }

        $remaining = array_slice($candidates, 0, self::MAX_STOPS);
        $origin = array_shift($remaining);

        $stops = [[
            'id' => $origin['id'],
            'latitude' => $origin['latitude'],
            'longitude' => $origin['longitude'],
            'distance_km' => 0.0,
        ]];

        $current = $origin;
        $total = 0.0;

        while ($remaining !== []) {
            $nearestIndex = null;
            $nearestDistance = null;

            foreach ($remaining as $index => $candidate) {
                $distance = $this->haversineKm(
                    $current['latitude'],
                    $current['longitude'],
                    $candidate['latitude'],
                    $candidate['longitude'],
                );

                if (
                    $nearestDistance === null
                    || $distance < $nearestDistance
                    || ($distance === $nearestDistance && $candidate['id'] < $remaining[$nearestIndex]['id'])
                ) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            $next = $remaining[$nearestIndex];
            unset($remaining[$nearestIndex]);
            $remaining = array_values($remaining);

            $total += $nearestDistance;

            $stops[] = [
                'id' => $next['id'],
                'latitude' => $next['latitude'],
                'longitude' => $next['longitude'],
                'distance_km' => round($nearestDistance, 4),
            ];

            $current = $next;
        }

        return [
            'stops' => $stops,
            'total_distance_km' => round($total, 4),
        ];
    }

    /**
     * Great-circle distance between two coordinates in kilometers.
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
