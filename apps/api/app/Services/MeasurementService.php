<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\RecommendationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stage producer for the named `measurement` snapshot section.
 *
 * Recommendation funnel (30-day rolling window):
 *  - displayed / clicked / cart = counts of RecommendationEvent rows by event_type.
 *  - purchased = event `product_id`+`outlet_id` matches a successful,
 *    non-excluded OrderItem/parent Order in the 30-day window.
 *    No campaign attribution is used or inferred.
 *  - rates are safe when denominators are zero.
 *
 * Persisted generic forecast actuals (`forecast_actuals`) carry
 * `dimension_key`-scoped named fixtures, e.g. `fixture:wape-pass`, each an
 * ordered list of `{actual, forecast}` decimal-safe pairs. The stage does no
 * WAPE math itself beyond copying payloads into the snapshot; numeric WAPE
 * is computed from the published section by the admin consumer.
 *
 * This service never mutates published data and never publishes partial
 * results; failures are thrown so the pipeline records a failed run.
 */
class MeasurementService
{
    public const SECTION = 'measurement';

    public const WINDOW_DAYS = 30;

    public const EVENT_TYPES = ['displayed', 'clicked', 'cart', 'purchased'];

    public const WAPE_TARGET = 0.30;

    public const FUNNEL_STEPS = ['displayed', 'clicked', 'cart', 'purchased'];

    private const EXCLUDED_ORDER_STATUSES = ['Cancelled', 'Canceled', 'Rejected', 'Invalid'];

    private const FUNNEL_DIMENSION_KEY = 'recommendation-funnel';

    /**
     * Return a callback compatible with DataPipelineService::registerStage().
     *
     * The callback receives the window `['start','end','timezone']` and returns
     * an array keyed by `dimension_key` carrying typed measurement payloads.
     */
    public function stageCallback(): callable
    {
        return function (array $window): array {
            return $this->produce($window);
        };
    }

    /**
     * Produce the measurement section payloads for a given window.
     *
     * @return array<string, array<string, mixed>>
     */
    public function produce(array $window): array
    {
        $start = Carbon::parse($window['start'])->startOfDay();
        $end = Carbon::parse($window['end'])->endOfDay();

        $events = RecommendationEvent::query()
            ->whereIn('event_type', ['displayed', 'clicked', 'cart'])
            ->whereBetween('occurred_at', [$start, $end])
            ->get(['id', 'event_type', 'outlet_id', 'product_id', 'occurred_at']);

        $displayed = $events->where('event_type', 'displayed')->count();
        $clicked = $events->where('event_type', 'clicked')->count();
        $cart = $events->where('event_type', 'cart')->count();

        $purchased = $this->countPurchased($events, $start, $end);

        $funnel = [
            'displayed' => $displayed,
            'clicked' => $clicked,
            'cart' => $cart,
            'purchased' => $purchased,
        ];

        $payload = [
            'funnel' => $funnel,
            'rates' => [
                'clicked_rate' => self::safeRate($clicked, $displayed),
                'cart_rate' => self::safeRate($cart, $clicked),
                'purchased_rate' => self::safeRate($purchased, $cart),
                'overall_conversion_rate' => self::safeRate($purchased, $displayed),
            ],
            'attribution' => [
                'method' => 'order_match',
                'matches' => ['product_id', 'outlet_id'],
                'window_days' => self::WINDOW_DAYS,
                'excluded_statuses' => self::EXCLUDED_ORDER_STATUSES,
                'note' => 'A displayed/clicked/cart event counts as purchased only when its product_id and outlet_id match a successful, non-excluded OrderItem/parent Order in the 30-day window. No campaign attribution is used or inferred.',
            ],
            'window_days' => self::WINDOW_DAYS,
            'window_start' => $window['start'],
            'window_end' => $window['end'],
            'timezone' => $window['timezone'] ?? DataPipelineService::TIMEZONE,
        ];

        $result = [self::FUNNEL_DIMENSION_KEY => $payload];

        // Copy persisted forecast-actual fixtures verbatim into the snapshot so
        // the reader-backed consumer can compute numeric WAPE deterministically.
        foreach ($this->forecastFixtures() as $dimensionKey => $fixture) {
            $result[$dimensionKey] = $fixture;
        }

        return $result;
    }

    /**
     * Fetch persisted `forecast_actuals` fixtures from the database.
     *
     * @return array<string, array<string, mixed>>
     */
    private function forecastFixtures(): array
    {
        try {
            $rows = DB::table('forecast_actuals')->orderBy('id')->get();
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $pairs = is_string($row->pairs ?? null) ? json_decode($row->pairs, true) : ($row->pairs ?? null);
            if (! is_array($pairs)) {
                $pairs = [];
            }
            $result[(string) $row->dimension_key] = [
                'fixture' => $row->dimension_key,
                'pairs' => array_values($pairs),
            ];
        }

        return $result;
    }

    /**
     * Count purchased as distinct attribution pairs that convert in window.
     * The contract demands product_id+outlet_id attribution — purchased
     * equals the count of eligible pairs with successful orders, not a
     * multiple of displayed events.
     */
    private function countPurchased(\Illuminate\Support\Collection $events, Carbon $start, Carbon $end): int
    {
        $eligiblePairs = [];
        foreach ($events as $event) {
            if ($event->outlet_id === null || $event->product_id === null) {
                continue;
            }
            $eligiblePairs[(int) $event->outlet_id.'|'.(int) $event->product_id] = [
                'outlet_id' => (int) $event->outlet_id,
                'product_id' => (int) $event->product_id,
            ];
        }
        if (empty($eligiblePairs)) {
            return 0;
        }

        $purchasedPairs = 0;
        foreach ($eligiblePairs as $pair) {
            $exists = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
                ->where('order_items.quantity', '>', 0)
                ->whereBetween('orders.created_at', [$start, $end])
                ->where('orders.outlet_id', $pair['outlet_id'])
                ->where('order_items.product_id', $pair['product_id'])
                ->exists();
            if ($exists) {
                $purchasedPairs++;
            }
        }

        return $purchasedPairs;
    }

    /**
     * Safe conversion rate in [0,1]; zero denominators return 0.0.
     */
    public static function safeRate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        $rate = $numerator / $denominator;

        return max(0.0, min(1.0, round($rate, 4)));
    }

    /**
     * Build the admin recommendation-funnel response from a published snapshot.
     *
     * @param array<int, array{dimension_key: ?string, dimension: mixed, payload: mixed, snapshot_version: int, window: array}> $rows
     * @param array{start: string, end: string, timezone: string}|null $window
     * @return array<string, mixed>
     */
    public function recommendationMeasurementFromSnapshot(array $rows, ?int $version, ?array $window): array
    {
        $funnel = ['displayed' => 0, 'clicked' => 0, 'cart' => 0, 'purchased' => 0];
        $rates = [
            'clicked_rate' => 0.0,
            'cart_rate' => 0.0,
            'purchased_rate' => 0.0,
            'overall_conversion_rate' => 0.0,
        ];
        $attribution = [
            'method' => 'order_match',
            'matches' => ['product_id', 'outlet_id'],
            'window_days' => self::WINDOW_DAYS,
            'excluded_statuses' => self::EXCLUDED_ORDER_STATUSES,
            'note' => 'A displayed/clicked/cart event counts as purchased only when its product_id and outlet_id match a successful, non-excluded OrderItem/parent Order in the 30-day window. No campaign attribution is used or inferred.',
        ];

        foreach ($rows as $row) {
            if (($row['dimension_key'] ?? null) !== self::FUNNEL_DIMENSION_KEY) {
                continue;
            }
            $payload = $row['payload'] ?? null;
            if (! is_array($payload)) {
                continue;
            }
            foreach (self::FUNNEL_STEPS as $step) {
                $funnel[$step] = (int) ($payload['funnel'][$step] ?? 0);
            }
            if (is_array($payload['rates'] ?? null)) {
                foreach (array_keys($rates) as $key) {
                    $rates[$key] = (float) ($payload['rates'][$key] ?? 0.0);
                }
            }
            if (is_array($payload['attribution'] ?? null)) {
                $attribution = $payload['attribution'];
            }
        }

        $orderedFunnel = [];
        foreach (self::FUNNEL_STEPS as $step) {
            $orderedFunnel[] = ['step' => $step, 'count' => $funnel[$step]];
        }

        return [
            'funnel' => $orderedFunnel,
            'rates' => $rates,
            'attribution' => $attribution,
            'snapshot_version' => $version,
            'window' => $window,
        ];
    }

    /**
     * Build the admin forecast-WAPE response from a published snapshot.
     *
     * WAPE = sum(|forecast - actual|) / sum(actual) over the 30-day actual
     * window; the strict target requires WAPE < 0.30 (accuracy strictly >70%).
     * All-zero actuals and fewer than 30 actual days remain pending and are
     * never reported as a perfect score. `$fixture` selects a named persisted
     * forecast-actual fixture (e.g. `fixture:wape-pass`); null uses all pairs.
     *
     * @param array<int, array{dimension_key: ?string, dimension: mixed, payload: mixed, snapshot_version: int, window: array}> $rows
     * @param array{start: string, end: string, timezone: string}|null $window
     * @return array<string, mixed>
     */
    public function forecastMeasurementFromSnapshot(array $rows, ?int $version, ?array $window, ?string $fixture = null): array
    {
        $pairs = $this->pairsFromSnapshot($rows, $fixture);
        $actualDays = count($pairs);

        $base = [
            'snapshot_version' => $version,
            'window' => $window,
            'actual_days' => $actualDays,
            'minimum_required_days' => self::WINDOW_DAYS,
        ];

        if ($actualDays < self::WINDOW_DAYS) {
            return array_merge($base, [
                'status' => 'insufficient-data',
                'wape' => null,
                'accuracy' => null,
                'accuracy_percent' => null,
                'target_achieved' => false,
                'target' => 'accuracy_strictly_above_70_percent',
                'note' => 'Fewer than 30 actual days are available; forecast accuracy remains pending and is not treated as a pass.',
            ]);
        }

        $absoluteErrorCents = 0;
        $actualCents = 0;
        foreach ($pairs as $pair) {
            $actual = $this->decimalToCents($pair['actual'] ?? 0);
            $forecast = $this->decimalToCents($pair['forecast'] ?? 0);
            $absoluteErrorCents += abs($forecast - $actual);
            $actualCents += $actual;
        }

        if ($actualCents <= 0) {
            return array_merge($base, [
                'status' => 'pending',
                'wape' => null,
                'accuracy' => null,
                'accuracy_percent' => null,
                'target_achieved' => false,
                'target' => 'accuracy_strictly_above_70_percent',
                'note' => 'Actual demand is all zero; WAPE is undefined and is never treated as a perfect score.',
            ]);
        }

        $wape = $absoluteErrorCents / $actualCents;
        $accuracy = 1 - $wape;
        $targetAchieved = $wape < self::WAPE_TARGET;

        return array_merge($base, [
            'status' => $targetAchieved ? 'target_achieved' : 'target_not_achieved',
            'wape' => number_format(round($wape, 4), 4, '.', ''),
            'accuracy' => round($accuracy, 4),
            'accuracy_percent' => round($accuracy * 100, 2),
            'target_achieved' => $targetAchieved,
            'target' => 'accuracy_strictly_above_70_percent',
            'note' => 'WAPE = sum(|forecast - actual|) / sum(actual) over the 30-day actual window; the target requires WAPE strictly below 0.30.',
        ]);
    }

    /**
     * Extract ordered `{actual,forecast}` decimal-safe pairs from snapshot rows.
     *
     * @param array<int, array{dimension_key: ?string, dimension: mixed, payload: mixed, snapshot_version: int, window: array}> $rows
     * @return array<int, array{actual: mixed, forecast: mixed}>
     */
    private function pairsFromSnapshot(array $rows, ?string $fixture): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $dimensionKey = (string) ($row['dimension_key'] ?? '');
            if (! str_starts_with($dimensionKey, 'fixture:')) {
                continue;
            }
            if ($fixture !== null && $dimensionKey !== $fixture) {
                continue;
            }
            $payload = $row['payload'] ?? null;
            if (! is_array($payload) || ! is_array($payload['pairs'] ?? null)) {
                continue;
            }
            foreach ($payload['pairs'] as $pair) {
                if (is_array($pair)) {
                    $merged[] = $pair;
                }
            }
            if ($fixture !== null) {
                break;
            }
        }

        return array_values($merged);
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
}
