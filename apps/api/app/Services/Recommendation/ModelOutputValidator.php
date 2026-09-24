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

        foreach ($output['recommendations'] as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('Each recommendation must be an array.');
            }
            foreach (self::REQUIRED_ITEM_FIELDS as $field) {
                if (! array_key_exists($field, $item)) {
                    throw new InvalidArgumentException("Missing recommendation field: {$field}");
                }
            }
        }
    }
}
