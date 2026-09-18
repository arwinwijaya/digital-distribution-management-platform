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

    private const NEEDS_ATTENTION_LIMIT = 5;

    private const SALES_DECLINE_THRESHOLD_PERCENT = 20.0;

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

    // ─────────────────────────────────────────────────────────────────────────
    // Insight composition (Analitik home). Everything below up to the shared
    // aggregate helpers is new for the strategic insight payload; the Dashboard
    // path (`dashboard()`) is untouched.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalise a window metric to integer cents so every delta is computed on
     * the shared cents path. Order counts are already integers; money strings go
     * through `decimalToCents()`.
     */
    private function metricCents(int|string $value): int
    {
        return is_int($value) ? $value : $this->decimalToCents($value);
    }

    /**
     * Compose the fixed-window strategic insight payload for the Analitik home.
     *
     * Current window = end-29..end (30 days inclusive); previous window is the
     * equal-length block immediately before it (end-59..end-30). Reuses the same
     * aggregate helpers as the Dashboard so both surfaces share one source of
     * truth for money and status handling.
     *
     * @return array<string, mixed>
     */
    public function insight(CarbonInterface $end): array
    {
        $currentStart = $end->copy()->subDays(29)->startOfDay();
        $currentEnd = $end->copy()->endOfDay();
        $prevEnd = $end->copy()->subDays(30)->endOfDay();
        $prevStart = $end->copy()->subDays(59)->startOfDay();

        $current = $this->windowMetrics($currentStart, $currentEnd);
        $previous = $this->windowMetrics($prevStart, $prevEnd);
        $outletPerformance = $this->outletPerformanceWithTotal($currentStart, $currentEnd);

        $metricsDelta = [];
        foreach (['orders_total', 'sales_total', 'payments_total', 'outstanding_total'] as $key) {
            $metricsDelta[$key] = $this->deltaFor(
                $this->metricCents($current[$key]),
                $this->metricCents($previous[$key]),
            );
        }

        return [
            'comparison' => [
                'period' => [
                    'start_date' => $currentStart->toDateString(),
                    'end_date' => $end->toDateString(),
                ],
                'previous_period' => [
                    'start_date' => $prevStart->toDateString(),
                    'end_date' => $prevEnd->toDateString(),
                ],
            ],
            'metrics' => [
                'orders_total' => $current['orders_total'],
                'sales_total' => $current['sales_total'],
                'outlets_total' => (int) Outlet::query()->where('is_active', true)->count(),
                'products_total' => (int) Product::query()->where('is_active', true)->count(),
                'payments_total' => $current['payments_total'],
                'outstanding_total' => $current['outstanding_total'],
            ],
            'metrics_delta' => $metricsDelta,
            'needs_attention' => $this->needsAttention($currentStart, $currentEnd, $prevStart, $prevEnd),
            'sales_trends' => $this->zeroFilledTrends($currentStart, $currentEnd),
            'outlet_performance' => $outletPerformance['rows'],
            'outlet_performance_total' => $outletPerformance['total'],
            'outlet_performance_has_more' => $outletPerformance['total'] > self::OUTLET_PERFORMANCE_LIMIT,
        ];
    }

    /**
     * Daily trend with every date in the window present; days without orders are
     * zero-filled. The shared `salesTrends()` helper stays sparse (Dashboard
     * contract) — padding happens only here.
     *
     * @return array<int, array<string, int|string>>
     */
    private function zeroFilledTrends(CarbonInterface $start, CarbonInterface $end): array
    {
        $sparse = collect($this->salesTrends($start, $end, 'daily'))->keyBy('period');

        $filled = [];
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lessThanOrEqualTo($last)) {
            $period = $cursor->toDateString();
            $filled[] = $sparse->get($period, [
                'period' => $period,
                'orders_total' => 0,
                'sales_total' => '0.00',
                'payments_total' => '0.00',
            ]);
            $cursor->addDay();
        }

        return $filled;
    }

    /**
     * Top-10 ranked rows (deterministic tie-break) plus the total distinct outlet
     * count in the current window, so the UI can render "dan N outlet lain".
     *
     * @return array{rows: array<int, array<string, int|string>>, total: int}
     */
    private function outletPerformanceWithTotal(CarbonInterface $start, CarbonInterface $end): array
    {
        $total = (int) $this->orderQuery($start, $end)->distinct()->count('outlet_id');

        return [
            'rows' => $this->outletPerformance($start, $end)['rows'],
            'total' => $total,
        ];
    }

    /**
     * Qualify outlets that need attention. At this step only qualification and
     * the item shape are produced (no cap, no ordering, no de-duplication yet).
     *
     * @return array<int, array<string, mixed>>
     */
    private function needsAttention(
        CarbonInterface $currentStart,
        CarbonInterface $currentEnd,
        CarbonInterface $prevStart,
        CarbonInterface $prevEnd,
    ): array {
        $current = $this->outletSalesCentsByOutlet($currentStart, $currentEnd);
        $previous = $this->outletSalesCentsByOutlet($prevStart, $prevEnd);
        $outstanding = $this->pointInTimeOutstandingByOutlet();

        $outletIds = array_values(array_unique(array_merge(
            array_keys($current),
            array_keys($previous),
            array_keys($outstanding),
        )));
        $names = $this->outletNames($outletIds);

        $items = [];
        foreach ($outletIds as $outletId) {
            $previousCents = (int) ($previous[$outletId] ?? 0);
            $currentCents = (int) ($current[$outletId] ?? 0);
            $outstandingCents = (int) ($outstanding[$outletId] ?? 0);

            $declineDelta = $this->unroundedDeltaPercent($currentCents, $previousCents);
            $declines = $declineDelta !== null && $declineDelta <= -self::SALES_DECLINE_THRESHOLD_PERCENT;
            $hasOutstanding = $outstandingCents > 0;

            if (! $declines && ! $hasOutstanding) {
                continue;
            }

            $items[] = [
                'outlet_id' => (int) $outletId,
                'outlet_name' => $names[$outletId] ?? '',
                'reason' => $declines ? 'sales_decline' : 'outstanding_risk',
                'delta_percent' => $declines ? round($declineDelta, 1) : null,
                'outstanding_total' => $this->moneyFromCents($outstandingCents),
                '_severity' => $declines ? abs($declineDelta) : null,
                '_outstanding_cents' => $outstandingCents,
            ];
        }

        // Deterministic ordering: sales_decline first (by unrounded magnitude
        // descending), then outstanding_risk (by outstanding cents descending),
        // each tie-broken by outlet name ascending then outlet id ascending.
        usort($items, function (array $a, array $b): int {
            $aDecline = $a['reason'] === 'sales_decline';
            $bDecline = $b['reason'] === 'sales_decline';
            if ($aDecline !== $bDecline) {
                return $aDecline ? -1 : 1;
            }

            if ($aDecline) {
                if ($a['_severity'] !== $b['_severity']) {
                    return $a['_severity'] < $b['_severity'] ? 1 : -1;
                }
            } elseif ($a['_outstanding_cents'] !== $b['_outstanding_cents']) {
                return $a['_outstanding_cents'] < $b['_outstanding_cents'] ? 1 : -1;
            }

            $byName = strcmp((string) $a['outlet_name'], (string) $b['outlet_name']);

            return $byName !== 0 ? $byName : ($a['outlet_id'] <=> $b['outlet_id']);
        });

        $items = array_slice($items, 0, self::NEEDS_ATTENTION_LIMIT);

        return array_map(function (array $item): array {
            unset($item['_severity'], $item['_outstanding_cents']);

            return $item;
        }, $items);
    }

    /**
     * Sum non-excluded order sales per outlet, keyed by outlet id, in cents.
     *
     * @return array<int, int>
     */
    private function outletSalesCentsByOutlet(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = $this->orderQuery($start, $end)
            ->selectRaw('outlet_id, COALESCE(SUM(total_amount), 0) as sales_total')
            ->groupBy('outlet_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->outlet_id] = $this->decimalToCents($row->sales_total);
        }

        return $result;
    }

    /**
     * Point-in-time outstanding per outlet: every order still carrying a balance,
     * regardless of when it was created. Uses the documented allow-list only.
     *
     * @return array<int, int>
     */
    private function pointInTimeOutstandingByOutlet(): array
    {
        $rows = Order::query()
            ->whereIn('status', self::OUTSTANDING_ORDER_STATUSES)
            ->selectRaw('outlet_id, COALESCE(SUM(CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END), 0) as outstanding_total')
            ->groupBy('outlet_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $cents = $this->decimalToCents($row->outstanding_total);
            if ($cents > 0) {
                $result[(int) $row->outlet_id] = $cents;
            }
        }

        return $result;
    }

    /** @param array<int, int|string> $outletIds @return array<int, string> */
    private function outletNames(array $outletIds): array
    {
        return Outlet::query()
            ->whereIn('id', $outletIds)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => (string) $name])
            ->all();
    }

    /** @return array{orders_total:int, sales_total:string, payments_total:string, outstanding_total:string} */
    private function windowMetrics(CarbonInterface $start, CarbonInterface $end): array
    {
        $orderSummary = $this->orderQuery($start, $end)
            ->selectRaw('COUNT(*) as orders_total, COALESCE(SUM(total_amount), 0) as sales_total')
            ->first();
        $outstanding = $this->outstandingOrderQuery($start, $end)
            ->selectRaw('COALESCE(SUM(CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END), 0) as outstanding_total')
            ->value('outstanding_total');
        $paymentTotal = $this->paymentQuery($start, $end)->sum('payments.amount');

        return [
            'orders_total' => (int) ($orderSummary->orders_total ?? 0),
            'sales_total' => $this->money($orderSummary->sales_total ?? 0),
            'payments_total' => $this->money($paymentTotal),
            'outstanding_total' => $this->money($outstanding),
        ];
    }

    /**
     * Delta in percent rounded to one decimal, with direction derived from the
     * UNROUNDED ratio so a chip can never read "up" while showing 0,0%.
     * A zero previous baseline yields a null delta (no fake percentage).
     *
     * @return array{delta_percent: float|null, direction: string}
     */
    private function deltaFor(int $currentCents, int $previousCents): array
    {
        $unrounded = $this->unroundedDeltaPercent($currentCents, $previousCents);
        if ($unrounded === null) {
            return ['delta_percent' => null, 'direction' => 'neutral'];
        }

        $direction = match (true) {
            $unrounded > 0 => 'up',
            $unrounded < 0 => 'down',
            default => 'neutral',
        };

        return ['delta_percent' => round($unrounded, 1), 'direction' => $direction];
    }

    /**
     * Unrounded percentage change, or null when there is no baseline to compare
     * against (previous == 0). Shared by the metric deltas and the needs_attention
     * decline threshold so both evaluate the same unrounded value.
     */
    private function unroundedDeltaPercent(int $currentCents, int $previousCents): ?float
    {
        if ($previousCents === 0) {
            return null;
        }

        return (($currentCents - $previousCents) / $previousCents) * 100;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared aggregate helpers (used by both `dashboard()` and `insight()`).
    // Behaviour here is part of the Dashboard contract — do not change it.
    // ─────────────────────────────────────────────────────────────────────────

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
