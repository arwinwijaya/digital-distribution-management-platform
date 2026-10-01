<?php

namespace App\Services;

use App\Models\Order;
use App\Support\ListQuery;
use App\Support\OrderFormatter;
use App\Support\OrderListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderListBuilder
{
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'order_id', 'status', 'total_amount', 'id'];

    /**
     * @param  array<string,mixed>  $filters
     * @return array{orders: mixed, meta: array<string,mixed>}
     */
    public function build(Request $request, array $filters): array
    {
        [$limit, $cursor] = $this->resolveLimitAndCursor($request);
        [$sortColumn, $sortOrder] = $this->resolveSorting($request);
        $query = $this->applyFilters($filters);
        $meta = $this->buildMeta($query, $limit, $cursor);
        $raw = $this->fetchPage($query, $sortColumn, $sortOrder, $limit, $cursor);

        return [
            'orders' => $raw->take($limit)->map(
                fn (Order $row) => app(OrderFormatter::class)->format($row),
            ),
            'meta' => array_merge(['has_more' => $raw->count() > $limit], $meta),
        ];
    }

    /** @return array{0:int,1:int} */
    private function resolveLimitAndCursor(Request $request): array
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        return [$limit, $cursor];
    }

    /** @return array{0:string,1:string} */
    private function resolveSorting(Request $request): array
    {
        return ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'),
            'created_at',
            'desc',
        );
    }

    /** @param array<string,mixed> $filters */
    private function applyFilters(array $filters): Builder
    {
        $query = Order::query()->with(['items.product']);
        app(OrderListFilters::class)->apply($query, $filters);

        return $query;
    }

    /** @return array<string,mixed> */
    private function buildMeta(Builder $query, int $limit, int $cursor): array
    {
        return array_merge(
            ['limit' => $limit, 'cursor' => $cursor],
            ListQuery::meta((clone $query)->count()),
        );
    }

    private function fetchPage(Builder $query, string $sortColumn, string $sortOrder, int $limit, int $cursor): mixed
    {
        return $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();
    }
}
