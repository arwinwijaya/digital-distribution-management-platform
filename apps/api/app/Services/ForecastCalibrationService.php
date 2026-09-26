<?php

namespace App\Services;

use App\Models\ForecastCalibration;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic, decimal-safe forecast calibration.
 *
 * Reads ordered `{actual,forecast}` pairs persisted in `forecast_actuals` for a
 * `dimension_key` and derives a simple multiplicative bias factor
 * `sum(actual) / sum(forecast)`. With fewer than MIN_DATA_POINTS pairs, or a
 * non-positive forecast total, it stores a neutral fallback (factor 1.0) and
 * marks `fallback = true` rather than pretending a perfect score.
 *
 * The stored calibration is keyed uniquely by `dimension_key` and upserted, so
 * repeated calls are idempotent. This service never mutates ForecastService.
 */
class ForecastCalibrationService
{
    public const MIN_DATA_POINTS = 3;

    public const METHOD_VERSION = 'bias-ratio-v1';

    public const NEUTRAL_FACTOR = 1.0;

    public function __construct(
        private ?ForecastService $forecast = null,
    ) {
        $this->forecast ??= app(ForecastService::class);
    }

    /**
     * Calibrate a dimension and persist the result.
     *
     * @return array{dimension_key: string, bias_factor: float, seasonality_factor: float, method_version: string, fallback: bool, sample_size: int}
     */
    public function calibrate(string $dimensionKey): array
    {
        $pairs = $this->pairs($dimensionKey);
        $sampleSize = count($pairs);

        $actualCents = 0;
        $forecastCents = 0;
        foreach ($pairs as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $actualCents += $this->decimalToCents($pair['actual'] ?? 0);
            $forecastCents += $this->decimalToCents($pair['forecast'] ?? 0);
        }

        $insufficient = $sampleSize < self::MIN_DATA_POINTS || $forecastCents <= 0;
        $biasFactor = $insufficient
            ? self::NEUTRAL_FACTOR
            : round($actualCents / $forecastCents, 6);

        $calibration = ForecastCalibration::updateOrCreate(
            ['dimension_key' => $dimensionKey],
            [
                'bias_factor' => number_format($biasFactor, 6, '.', ''),
                'seasonality_factor' => number_format(self::NEUTRAL_FACTOR, 6, '.', ''),
                'method_version' => self::METHOD_VERSION,
                'fallback' => $insufficient,
                'sample_size' => $sampleSize,
                'metadata' => [
                    'method' => 'bias_ratio',
                    'actual_cents' => $actualCents,
                    'forecast_cents' => $forecastCents,
                    'minimum_required' => self::MIN_DATA_POINTS,
                    'note' => $insufficient
                        ? 'Insufficient forecast/actual history; neutral factor 1.0 applied and calibration flagged as fallback.'
                        : 'Bias factor = sum(actual) / sum(forecast) over persisted pairs; deterministic, non-probabilistic.',
                ],
            ]
        );

        return [
            'dimension_key' => $dimensionKey,
            'bias_factor' => (float) $calibration->bias_factor,
            'seasonality_factor' => (float) $calibration->seasonality_factor,
            'method_version' => $calibration->method_version,
            'fallback' => (bool) $calibration->fallback,
            'sample_size' => (int) $calibration->sample_size,
        ];
    }

    /**
     * Apply the persisted calibration factor for a dimension to a ForecastService result.
     *
     * Decimal-safe: money fields are converted to cents, scaled, then formatted
     * back to a two-decimal string. When no calibration exists (or it is a
     * fallback), the neutral factor 1.0 leaves the base value unchanged.
     *
     * @param  array<string, mixed>  $forecastResult
     * @return array<string, mixed>
     */
    public function apply(array $forecastResult, string $dimensionKey): array
    {
        $calibration = ForecastCalibration::where('dimension_key', $dimensionKey)->first();
        $factor = $calibration !== null ? (float) $calibration->bias_factor : self::NEUTRAL_FACTOR;
        $methodVersion = $calibration?->method_version ?? self::METHOD_VERSION;

        if (array_key_exists('forecast_sales', $forecastResult)) {
            $baseCents = $this->decimalToCents($forecastResult['forecast_sales'] ?? 0);
            $scaledCents = (int) round($baseCents * $factor);
            $forecastResult['forecast_sales'] = $this->moneyFromCents($scaledCents);
        }

        $forecastResult['dimension_key'] = $dimensionKey;
        $forecastResult['method_version'] = $methodVersion;
        $forecastResult['calibration'] = [
            'factor' => $factor,
            'fallback' => $calibration === null ? true : (bool) $calibration->fallback,
            'method_version' => $methodVersion,
        ];

        return $forecastResult;
    }

    /**
     * @return array<int, array{actual: mixed, forecast: mixed}>
     */
    private function pairs(string $dimensionKey): array
    {
        $row = DB::table('forecast_actuals')->where('dimension_key', $dimensionKey)->first();
        if ($row === null) {
            return [];
        }

        $pairs = is_string($row->pairs ?? null) ? json_decode($row->pairs, true) : ($row->pairs ?? null);

        return is_array($pairs) ? array_values($pairs) : [];
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
