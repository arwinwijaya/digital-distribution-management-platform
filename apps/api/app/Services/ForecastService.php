<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic forecast using a grouped historical mean. It is deliberately
 * conservative: fewer than three positive historical periods returns no
 * predictions and a low-confidence fallback.
 */
class ForecastService
{
    public const MIN_DATA_POINTS = 3;

    public const MAX_HISTORY_BUCKETS = 366;

    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    /**
     * @return array<string, mixed>
     */
    public function forecast(?int $outletId, string $period, int $horizon): array
    {
        $dailyRows = Order::query()
            ->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES)
            ->where('total_amount', '>', 0)
            ->when($outletId !== null, fn ($query) => $query->where('outlet_id', $outletId))
            ->selectRaw('DATE(created_at) as period, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as sales_total')
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('period')
            ->limit(self::MAX_HISTORY_BUCKETS)
            ->get()
            ->reverse()
            ->values();

        $points = $this->groupPoints($dailyRows, $period);
        $dataPoints = count($points);
        $sufficient = $dataPoints >= self::MIN_DATA_POINTS;
        $base = [
            'period' => $period,
            'horizon' => $horizon,
            'predictions' => [],
            'confidence' => $sufficient ? 'medium' : 'low',
            'confidence_score' => $sufficient ? 0.5 : 0.2,
            'data_points' => $dataPoints,
            'data_sufficiency' => [
                'sufficient' => $sufficient,
                'level' => $dataPoints === 0 ? 'none' : ($sufficient ? 'sufficient' : 'limited'),
                'minimum_required' => self::MIN_DATA_POINTS,
            ],
            'fallback' => ! $sufficient,
            'method' => 'historical_mean_v1',
            'method_version' => '1.0.0',
            'measurement' => [
                'measured' => false,
                'acceptance_target' => null,
                'accuracy_target' => null,
                'note' => 'Forecast accuracy requires measured future outcomes; confidence describes data sufficiency only.',
            ],
        ];

        if (! $sufficient) {
            return $base;
        }

        $salesCents = array_sum(array_map(fn (array $point): int => $this->decimalToCents($point['sales_total']), $points));
        $averageSalesCents = intdiv($salesCents, $dataPoints);
        $averageOrders = round(array_sum(array_column($points, 'order_count')) / $dataPoints, 2);
        $lastPeriod = Carbon::parse($points[$dataPoints - 1]['period']);
        $predictions = [];

        for ($index = 1; $index <= $horizon; $index++) {
            $date = $this->nextPeriod($lastPeriod, $period, $index);
            $predictions[] = [
                'period' => $date->toDateString(),
                'forecast_sales' => $this->moneyFromCents($averageSalesCents),
                'forecast_orders' => $averageOrders,
            ];
        }

        $base['predictions'] = $predictions;

        return $base;
    }

    /** @return array<int, array{period: string, order_count: int, sales_total: string}> */
    private function groupPoints(Collection $rows, string $period): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $date = Carbon::parse((string) $row->period);
            $key = match ($period) {
                'weekly' => $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
                'monthly' => $date->copy()->startOfMonth()->toDateString(),
                default => $date->toDateString(),
            };
            $grouped[$key]['order_count'] = ($grouped[$key]['order_count'] ?? 0) + (int) $row->order_count;
            $grouped[$key]['sales_cents'] = ($grouped[$key]['sales_cents'] ?? 0) + $this->decimalToCents($row->sales_total);
        }

        ksort($grouped);

        return array_map(
            fn (array $row, string $key): array => [
                'period' => $key,
                'order_count' => $row['order_count'],
                'sales_total' => $this->moneyFromCents($row['sales_cents']),
            ],
            $grouped,
            array_keys($grouped),
        );
    }

    private function nextPeriod(Carbon $last, string $period, int $increment): Carbon
    {
        return match ($period) {
            'weekly' => $last->copy()->addWeeks($increment),
            'monthly' => $last->copy()->addMonthsNoOverflow($increment),
            default => $last->copy()->addDays($increment),
        };
    }

    private function decimalToCents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) ($whole ?: 0) * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function moneyFromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
