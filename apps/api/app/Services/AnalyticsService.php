<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsService
{
    public const MAX_DATE_RANGE_DAYS = 366;
    public const MAX_TREND_BUCKETS = 366;
    public const OUTLET_PERFORMANCE_LIMIT = 10;

    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];
    private const OUTSTANDING_ORDER_STATUSES = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];

    /**
     * Build the owner dashboard from database aggregates. Queries return grouped
     * rows only; order, payment, outlet, and product tables are never loaded in
     * full into application memory.
     *
     * @return array{metrics: array<string, int|string>, sales_trends: array<int, array<string, int|string>>, outlet_performance: array<int, array<string, int|string>>}
     */
    public function dashboard(CarbonInterface $start, CarbonInterface $end, string $group): array
    {
        $orders = $this->orderQuery($start, $end);
        $orderSummary = (clone $orders)->selectRaw('COUNT(*) as orders_total, COALESCE(SUM(total_amount), 0) as sales_total')->first();
        $outstanding = $this->outstandingOrderQuery($start, $end)
            ->selectRaw('COALESCE(SUM(CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END), 0) as outstanding_total')
            ->value('outstanding_total');

        $payments = $this->paymentQuery($start, $end);
        $paymentTotal = (clone $payments)->sum('payments.amount');

        $outletPerformance = $this->outletPerformance($start, $end);

        return [
            'metrics' => [
                'orders_total' => (int) ($orderSummary->orders_total ?? 0),
                'sales_total' => $this->money($orderSummary->sales_total ?? 0),
                'outlets_total' => (int) Outlet::query()->where('is_active', true)->count(),
                'products_total' => (int) Product::query()->where('is_active', true)->count(),
                'payments_total' => $this->money($paymentTotal),
                'outstanding_total' => $this->money($outstanding),
            ],
            'sales_trends' => $this->salesTrends($start, $end, $group),
            'outlet_performance' => $outletPerformance['rows'],
            'analytics_limits' => [
                'max_date_range_days' => self::MAX_DATE_RANGE_DAYS,
                'max_trend_buckets' => self::MAX_TREND_BUCKETS,
                'outlet_performance_limit' => self::OUTLET_PERFORMANCE_LIMIT,
                'outlet_performance_has_more' => $outletPerformance['has_more'],
            ],
        ];
    }

    private function orderQuery(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Order::query()
            ->whereNotIn('status', self::EXCLUDED_ORDER_STATUSES)
            ->whereBetween('created_at', [$start, $end]);
    }

    private function outstandingOrderQuery(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Order::query()
            ->whereIn('status', self::OUTSTANDING_ORDER_STATUSES)
            ->whereBetween('created_at', [$start, $end]);
    }

    private function paymentQuery(CarbonInterface $start, CarbonInterface $end): Builder
    {
        return Payment::query()
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'completed')
            ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
            ->whereBetween('payments.created_at', [$start, $end]);
    }

    /** @return array<int, array<string, int|string>> */
    private function salesTrends(CarbonInterface $start, CarbonInterface $end, string $group): array
    {
        $orderRows = $this->orderQuery($start, $end)
            ->selectRaw('DATE(orders.created_at) as period, COUNT(*) as orders_total, COALESCE(SUM(orders.total_amount), 0) as sales_total')
            ->groupByRaw('DATE(orders.created_at)')
            ->orderBy('period')
            ->limit(self::MAX_TREND_BUCKETS)
            ->get();

        $paymentRows = $this->paymentQuery($start, $end)
            ->selectRaw('DATE(payments.created_at) as period, COALESCE(SUM(payments.amount), 0) as payments_total')
            ->groupByRaw('DATE(payments.created_at)')
            ->orderBy('period')
            ->limit(self::MAX_TREND_BUCKETS)
            ->get();

        $grouped = [];
        foreach ($orderRows as $row) {
            $period = $this->periodKey((string) $row->period, $group);
            $grouped[$period]['orders_total'] = ($grouped[$period]['orders_total'] ?? 0) + (int) $row->orders_total;
            $grouped[$period]['sales_cents'] = ($grouped[$period]['sales_cents'] ?? 0) + $this->decimalToCents($row->sales_total);
        }
        foreach ($paymentRows as $row) {
            $period = $this->periodKey((string) $row->period, $group);
            $grouped[$period]['payments_cents'] = ($grouped[$period]['payments_cents'] ?? 0) + $this->decimalToCents($row->payments_total);
        }

        ksort($grouped);
        return array_map(function (array $row, string $period): array {
            return [
                'period' => $period,
                'orders_total' => (int) ($row['orders_total'] ?? 0),
                'sales_total' => $this->moneyFromCents((int) ($row['sales_cents'] ?? 0)),
                'payments_total' => $this->moneyFromCents((int) ($row['payments_cents'] ?? 0)),
            ];
        }, $grouped, array_keys($grouped));
    }

    /**
     * Rank outlets by aggregate order sales. Ties are deterministic: outlet
     * name ascending, then outlet id ascending. Cancelled/rejected orders are
     * excluded, while all other existing order statuses count as sales.
     * Return only the server-defined top N outlets. Fetching one extra grouped
     * row lets the response advertise whether a larger ranking exists without
     * loading the full ranking into application memory.
     *
     * @return array{rows: array<int, array<string, int|string>>, has_more: bool}
     */
    private function outletPerformance(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = Order::query()
            ->join('outlets', 'outlets.id', '=', 'orders.outlet_id')
            ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('outlets.id as outlet_id, outlets.name as outlet_name, COUNT(orders.id) as orders_total, COALESCE(SUM(orders.total_amount), 0) as sales_total')
            ->groupBy('outlets.id', 'outlets.name')
            ->orderByDesc('sales_total')
            ->orderBy('outlets.name')
            ->orderBy('outlets.id')
            ->limit(self::OUTLET_PERFORMANCE_LIMIT + 1)
            ->get();

        $hasMore = $rows->count() > self::OUTLET_PERFORMANCE_LIMIT;
        $rows = $rows->take(self::OUTLET_PERFORMANCE_LIMIT);

        return [
            'rows' => $rows->values()->map(function ($row, int $index): array {
                return [
                    'rank' => $index + 1,
                    'outlet_id' => (int) $row->outlet_id,
                    'outlet_name' => $row->outlet_name,
                    'orders_total' => (int) $row->orders_total,
                    'sales_total' => $this->money($row->sales_total),
                ];
            })->all(),
            'has_more' => $hasMore,
        ];
    }

    private function periodKey(string $date, string $group): string
    {
        $day = Carbon::parse($date);

        return match ($group) {
            'weekly' => $day->startOfWeek(Carbon::MONDAY)->toDateString(),
            'monthly' => $day->startOfMonth()->toDateString(),
            default => $day->toDateString(),
        };
    }

    private function money(mixed $amount): string
    {
        return $this->moneyFromCents($this->decimalToCents($amount));
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
        $whole = intdiv($cents, 100);
        $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        $formatted = number_format($whole, 0, '.', '').'.'.$fraction;

        return $negative ? '-'.$formatted : $formatted;
    }
}
