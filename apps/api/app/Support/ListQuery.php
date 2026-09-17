<?php

namespace App\Support;

use Illuminate\Http\Request;

class ListQuery
{
    /**
     * Read a query param as a string, returning the default for array/missing
     * input. Guards against `?sort[]=x` which would otherwise raise
     * "Array to string conversion" and yield HTTP 500.
     */
    public static function scalarString(Request $request, string $key, string $default): string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Resolve a sort column/order against an allowlist, with safe fallbacks.
     *
     * @param  array<int, string>  $allowlist
     * @return array{0: string, 1: string}
     */
    public static function resolveSort(
        array $allowlist,
        string $sort,
        string $order = 'desc',
        ?string $defaultSort = null,
        string $defaultOrder = 'desc',
    ): array {
        if (in_array($sort, $allowlist, true)) {
            return [$sort, self::normalizeOrder($order) ?? 'desc'];
        }

        return [$defaultSort ?? 'created_at', self::normalizeOrder($defaultOrder) ?? 'desc'];
    }

    /**
     * Normalize an order direction to `asc`/`desc` (case-insensitive), or null
     * when invalid.
     */
    private static function normalizeOrder(string $order): ?string
    {
        $order = strtolower($order);

        return in_array($order, ['asc', 'desc'], true) ? $order : null;
    }

    /**
     * Build a portable nulls-last ORDER BY expression (works identically in
     * SQLite and PostgreSQL): NULL rows sort last on both asc and desc, with
     * id DESC as a deterministic tiebreaker.
     */
    public static function rawOrder(string $column, string $order): string
    {
        $order = strtoupper($order);

        return sprintf('(%s IS NULL) ASC, %s %s, id DESC', $column, $column, $order);
    }

    /**
     * Clamp a cursor/offset into a valid, non-negative offset.
     *
     * The limit is part of the call-site contract (controllers pass the same
     * limit used for the page) but does not alter a non-negative offset.
     */
    public static function offset(?int $offset, int $limit): int
    {
        return max(0, (int) $offset);
    }

    /**
     * Build the top-level meta payload. `summary` is only present when the
     * caller actually computed a breakdown.
     *
     * @param  array<string, int|string>  $summary
     * @return array<string, mixed>
     */
    public static function meta(int $total, array $summary = []): array
    {
        $meta = ['total' => $total];

        if ($summary !== []) {
            $meta['summary'] = $summary;
        }

        return $meta;
    }
}
