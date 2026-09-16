<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SalesTarget;

class SalesPerformanceService
{
    /**
     * Order statuses that count toward achievement (revenue).
     * Cancelled and New are excluded.
     *
     * @var string[]
     */
    public const ACHIEVEMENT_STATUSES = [
        'Confirmed',
        'Delivered',
        'Paid',
        'Partially Paid',
    ];

    /**
     * Calculate sales performance for a sales user in a given period.
     *
     * Achievement = SUM(total_amount) of orders where sales_user_id = $salesUserId,
     * status IN ACHIEVEMENT_STATUSES (excluding Cancelled/New), and created_at
     * falls within the given YYYY-MM period.
     *
     * Percentage = achievement / target * 100 (rounded to 2 decimals), or 0.0
     * when target is null or zero (avoids division-by-zero).
     *
     * @param  string  $period  YYYY-MM format
     * @return array{target: float, achievement: float, percentage: float, order_count: int}
     */
    public function calculatePerformance(int $salesUserId, string $period): array
    {
        $targetRow = SalesTarget::where('user_id', $salesUserId)
            ->where('period', $period)
            ->first();

        $target = $targetRow ? (float) $targetRow->target_amount : 0.0;

        // Parse YYYY-MM into year + month components.
        [$year, $month] = $this->parsePeriod($period);

        $baseQuery = Order::where('sales_user_id', $salesUserId)
            ->whereIn('status', self::ACHIEVEMENT_STATUSES);

        if ($year !== null && $month !== null) {
            $baseQuery
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month);
        }

        $achievement = (float) $baseQuery->sum('total_amount');
        $orderCount = $baseQuery->count();

        $percentage = 0.0;
        if ($target > 0) {
            $percentage = round(($achievement / $target) * 100.0, 2);
        }

        return [
            'target'      => round($target, 2),
            'achievement' => round($achievement, 2),
            'percentage'  => $percentage,
            'order_count' => $orderCount,
        ];
    }

    /**
     * Parse a YYYY-MM period string into year + month ints.
     * Returns [null, null] when malformed.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function parsePeriod(string $period): array
    {
        $parts = explode('-', $period);
        if (count($parts) !== 2) {
            return [null, null];
        }
        $year  = (int) $parts[0];
        $month = (int) $parts[1];
        if ($year < 1 || $month < 1 || $month > 12) {
            return [null, null];
        }

        return [$year, $month];
    }
}
