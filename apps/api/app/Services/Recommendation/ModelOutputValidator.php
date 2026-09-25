<?php

namespace App\Services\Recommendation;

use InvalidArgumentException;

/**
 * Strict schema validator for adapter output.
 *
 * Rejects unknown fields and wrong types so untrusted ML/LLM output can never
 * be consumed unchecked. No raw prompt/response is stored or logged here.
 */
class ModelOutputValidator
{
    /** @var array<int, string> */
    private const REQUIRED_TOP_LEVEL = [
        'recommendations',
        'limit',
        'data_points',
        'data_sufficiency',
        'fallback',
        'method',
        'method_version',
        'measurement',
    ];

    /** @var array<int, string> */
    private const REQUIRED_ITEM_FIELDS = [
        'rank',
        'product_id',
        'name',
        'sku',
        'category',
        'price',
        'purchased_quantity',
        'order_count',
        'reason',
    ];

    /** @var array<int, string> */
    private const ALLOWED_DATA_SUFFICIENCY_KEYS = [
        'level',
        'window_days',
        'days_in_window',
        'recent_average_fallback',
        'sparse',
        'minimum_recommended',
        'note',
    ];

    /** @var array<int, string> */
    private const ALLOWED_MEASUREMENT_KEYS = [
        'measured',
        'acceptance_target',
        'accuracy_target',
        'note',
    ];

    /**
     * @param  array<string, mixed>  $output
     */
    public function isValid(array $output): bool
    {
        try {
            $this->assertValid($output);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $output
     *
     * @throws InvalidArgumentException
     */
    public function assertValid(array $output): void
    {
        foreach (array_keys($output) as $key) {
            if (! in_array($key, self::REQUIRED_TOP_LEVEL, true)) {
                throw new InvalidArgumentException("Unknown adapter output field: {$key}");
            }
        }

        foreach (self::REQUIRED_TOP_LEVEL as $key) {
            if (! array_key_exists($key, $output)) {
                throw new InvalidArgumentException("Missing adapter output field: {$key}");
            }
        }

        if (! is_array($output['recommendations'])) {
            throw new InvalidArgumentException('recommendations must be an array.');
        }
        if (! is_int($output['limit'])) {
            throw new InvalidArgumentException('limit must be an integer.');
        }
        if (! is_int($output['data_points'])) {
            throw new InvalidArgumentException('data_points must be an integer.');
        }
        if (! is_array($output['data_sufficiency'])) {
            throw new InvalidArgumentException('data_sufficiency must be an array.');
        }
        if (! is_bool($output['fallback'])) {
            throw new InvalidArgumentException('fallback must be a boolean.');
        }
        if (! is_string($output['method']) || $output['method'] === '') {
            throw new InvalidArgumentException('method must be a non-empty string.');
        }
        if (! is_string($output['method_version']) || $output['method_version'] === '') {
            throw new InvalidArgumentException('method_version must be a non-empty string.');
        }
        if (! is_array($output['measurement'])) {
            throw new InvalidArgumentException('measurement must be an array.');
        }

        $this->validateDataSufficiency($output['data_sufficiency']);
        $this->validateMeasurement($output['measurement']);

        foreach ($output['recommendations'] as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each recommendation must be an array.');
            }
            $this->validateRecommendationItem($item);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     *
     * @throws InvalidArgumentException
     */
    private function validateRecommendationItem(array $item): void
    {
        $itemKeys = array_keys($item);
        sort($itemKeys);
        $requiredSorted = self::REQUIRED_ITEM_FIELDS;
        sort($requiredSorted);

        if ($itemKeys !== $requiredSorted) {
            $unknown = array_diff($itemKeys, self::REQUIRED_ITEM_FIELDS);
            $missing = array_diff(self::REQUIRED_ITEM_FIELDS, $itemKeys);
            $msg = '';
            if ($unknown) {
                $msg .= 'Unknown recommendation fields: ' . implode(', ', $unknown);
            }
            if ($missing) {
                $msg .= ($msg ? '; ' : '') . 'Missing recommendation fields: ' . implode(', ', $missing);
            }
            throw new InvalidArgumentException($msg ?: 'Recommendation item keys must match exactly.');
        }

        // Type checks
        $this->assertIntNonNegative($item['rank'], 'rank');
        $this->assertIntNonNegative($item['product_id'], 'product_id');
        $this->assertIntNonNegative($item['purchased_quantity'], 'purchased_quantity');
        $this->assertIntNonNegative($item['order_count'], 'order_count');

        $this->assertNonEmptyString($item['name'], 'name');
        $this->assertNonEmptyString($item['sku'], 'sku');
        $this->assertNonEmptyString($item['category'], 'category');
        $this->assertNonEmptyString($item['reason'], 'reason');

        $this->assertNumericNonNegative($item['price'], 'price');
    }

    /**
     * @param  array<string, mixed>  $dataSufficiency
     *
     * @throws InvalidArgumentException
     */
    private function validateDataSufficiency(array $dataSufficiency): void
    {
        foreach (array_keys($dataSufficiency) as $key) {
            if (! in_array($key, self::ALLOWED_DATA_SUFFICIENCY_KEYS, true)) {
                throw new InvalidArgumentException("Unknown data_sufficiency field: {$key}");
            }
        }

        if (isset($dataSufficiency['level'])) {
            $this->assertNonEmptyString($dataSufficiency['level'], 'data_sufficiency.level');
        }
        if (isset($dataSufficiency['window_days'])) {
            $this->assertIntNonNegative($dataSufficiency['window_days'], 'data_sufficiency.window_days');
        }
        if (isset($dataSufficiency['days_in_window'])) {
            $this->assertIntNonNegative($dataSufficiency['days_in_window'], 'data_sufficiency.days_in_window');
        }
        if (isset($dataSufficiency['recent_average_fallback'])) {
            if (! is_bool($dataSufficiency['recent_average_fallback'])) {
                throw new InvalidArgumentException('data_sufficiency.recent_average_fallback must be a boolean.');
            }
        }
        if (isset($dataSufficiency['sparse'])) {
            if (! is_bool($dataSufficiency['sparse'])) {
                throw new InvalidArgumentException('data_sufficiency.sparse must be a boolean.');
            }
        }
        if (isset($dataSufficiency['minimum_recommended'])) {
            $this->assertIntNonNegative($dataSufficiency['minimum_recommended'], 'data_sufficiency.minimum_recommended');
        }
        if (isset($dataSufficiency['note'])) {
            $this->assertNonEmptyString($dataSufficiency['note'], 'data_sufficiency.note');
        }
    }

    /**
     * @param  array<string, mixed>  $measurement
     *
     * @throws InvalidArgumentException
     */
    private function validateMeasurement(array $measurement): void
    {
        foreach (array_keys($measurement) as $key) {
            if (! in_array($key, self::ALLOWED_MEASUREMENT_KEYS, true)) {
                throw new InvalidArgumentException("Unknown measurement field: {$key}");
            }
        }

        if (isset($measurement['measured'])) {
            if (! is_bool($measurement['measured'])) {
                throw new InvalidArgumentException('measurement.measured must be a boolean.');
            }
        }
        if (isset($measurement['acceptance_target'])) {
            if (! (is_null($measurement['acceptance_target']) || is_int($measurement['acceptance_target']) || is_float($measurement['acceptance_target']))) {
                throw new InvalidArgumentException('measurement.acceptance_target must be int, float, or null.');
            }
        }
        if (isset($measurement['accuracy_target'])) {
            if (! (is_null($measurement['accuracy_target']) || is_int($measurement['accuracy_target']) || is_float($measurement['accuracy_target']))) {
                throw new InvalidArgumentException('measurement.accuracy_target must be int, float, or null.');
            }
        }
        if (isset($measurement['note'])) {
            $this->assertNonEmptyString($measurement['note'], 'measurement.note');
        }
    }

    /**
     * @param  mixed  $value
     * @param  string  $field
     *
     * @throws InvalidArgumentException
     */
    private function assertIntNonNegative(mixed $value, string $field): void
    {
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative integer.");
        }
    }

    /**
     * @param  mixed  $value
     * @param  string  $field
     *
     * @throws InvalidArgumentException
     */
    private function assertNonEmptyString(mixed $value, string $field): void
    {
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("{$field} must be a non-empty string.");
        }
    }

    /**
     * @param  mixed  $value
     * @param  string  $field
     *
     * @throws InvalidArgumentException
     */
    private function assertNumericNonNegative(mixed $value, string $field): void
    {
        if (! (is_int($value) || is_float($value)) || $value < 0) {
            throw new InvalidArgumentException("{$field} must be a non-negative number (int or float).");
        }
    }
}
