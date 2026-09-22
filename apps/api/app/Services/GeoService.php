<?php

namespace App\Services;

/**
 * Small geodesic helpers shared by field-operations features (Phase 8).
 *
 * Coordinates are WGS-84 decimal degrees; distances use the haversine
 * great-circle formula, which is accurate enough for proximity guards
 * (visit radius) and short driver routes.
 */
class GeoService
{
    private const EARTH_RADIUS_M = 6371000.0;

    /**
     * Great-circle distance between two coordinates, in meters.
     */
    public function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Whether `$lat`/`$lon` sit within `$radiusMeters` of the target point.
     */
    public function withinRadius(
        float $lat,
        float $lon,
        float $targetLat,
        float $targetLon,
        float $radiusMeters,
    ): bool {
        return $this->distanceMeters($lat, $lon, $targetLat, $targetLon) <= $radiusMeters;
    }
}
