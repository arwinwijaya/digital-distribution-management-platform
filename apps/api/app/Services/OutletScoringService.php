<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use Carbon\CarbonImmutable;

/**
 * Synchronous outlet scoring.
 *
 * Formula: volume*0.4 + frequency*0.3 + recency*0.3
 * - Delivered orders only (sales-collected count same as self-serve)
 * - Zero orders => score 0
 */
class OutletScoringService
{
    public const WINDOW_DAYS = 90;
    public const VOLUME_MAX = 100;  // max order count considered for volume normalization
    public const FREQUENCY_CAP_PER_WEEK = 10.0;  // orders/week capped at this before scaling
    public const WEIGHT_VOLUME = 0.4;
    public const WEIGHT_FREQUENCY = 0.3;
    public const WEIGHT_RECENCY = 0.3;

    /**
     * Recalculate score for an outlet (Delivered orders only).
     * Persists and returns the score.
     */
    public function recalculate(Outlet $outlet): int
    {
        $outletId = $outlet->getKey();
        $outlet->refresh();

        $now = CarbonImmutable::now();
        $windowStart = $now->subDays(self::WINDOW_DAYS);

        // Delivered orders in last 90 days
        $delivered90 = Order::query()
            ->where('outlet_id', $outletId)
            ->where('status', 'Delivered')
            ->where('created_at', '>=', $windowStart)
            ->get();

        if ($delivered90->isEmpty()) {
            $score = 0;
            $outlet->update(['score' => $score]);
            return $score;
        }

        // Volume: orders in last 90 days normalized to 0-100 (capped at VOLUME_MAX)
        $count90 = $delivered90->count();
        $volume = min(100.0, ($count90 / self::VOLUME_MAX) * 100.0);

        // Frequency: orders per week in last 90 days, capped, scaled to 0-100
        $weeks = self::WINDOW_DAYS / 7.0;
        $ordersPerWeek = $count90 / $weeks;
        $capped = min(self::FREQUENCY_CAP_PER_WEEK, $ordersPerWeek);
        $frequency = ($capped / self::FREQUENCY_CAP_PER_WEEK) * 100.0;

        // Recency: days since last delivered order (within window), inverse
        $lastOrder = $delivered90->max('created_at');
        $recency = 0.0;
        if ($lastOrder !== null) {
            $daysSince = (float) abs($now->diffInDays(CarbonImmutable::parse($lastOrder), true));
            $recency = max(0.0, (1.0 - ($daysSince / self::WINDOW_DAYS)) * 100.0);
        }

        // Keep calculation deterministic with precise floating math
        $score = (int) round(
            ($volume * self::WEIGHT_VOLUME)
            + ($frequency * self::WEIGHT_FREQUENCY)
            + ($recency * self::WEIGHT_RECENCY)
        );

        $score = min(100, max(0, $score));

        $outlet->update(['score' => $score]);

        return $score;
    }
}
