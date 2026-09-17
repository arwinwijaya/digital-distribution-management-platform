<?php

namespace App\Support;

class ListQuery
{
    /**
     * Resolve a sort column/order against an allowlist, with safe fallbacks.
     *
     * @param  array<int, string>  $allowlist
     * @return array{0: string, 1: string}
     */
    public static function resolveSort(array $allowlist, string $sort, string $order = 'desc'): array
    {
        $column = in_array($sort, $allowlist, true) ? $sort : 'created_at';

        $order = strtolower($order);
        $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

        return [$column, $order];
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
