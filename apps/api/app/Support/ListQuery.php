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
}
