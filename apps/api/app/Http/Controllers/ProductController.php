<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Sortable columns allowlist (invalid values silently fall back to default).
     */
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'name', 'sku', 'price', 'stock_quantity', 'id'];

    /**
     * Hard cap for the public catalog when no explicit limit is requested.
     */
    private const DEFAULT_LIMIT = 100;

    /**
     * List products with optional search filter (public/legacy catalog).
     *
     * Additive: sort/order against an allowlist (default remains id ASC),
     * an offset cursor, and a search-aware meta.total. The legacy default
     * (id ASC, hard limit 100) is unchanged when the new params are absent.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyCatalogFilters(Product::query(), $request);

        $limit = $this->resolveLimit($request);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        // Aggregate total derives from the SAME filtered builder (before limit/offset/order).
        $meta = [
            'limit' => $limit,
            'cursor' => $cursor,
            'total' => (clone $query)->count(),
        ];

        // limit+1 technique: fetch one extra row to determine has_more.
        $rows = $query
            ->orderByRaw(ListQuery::rawOrder(...$this->resolveSort($request)))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => array_merge(['has_more' => $hasMore], $meta),
        ]);
    }

    /**
     * Apply the catalog filters (search + supplier eligibility) to the query.
     *
     * Supplier-less products stay eligible for legacy compatibility, but
     * products owned by an inactive supplier must never be exposed.
     */
    private function applyCatalogFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->search($request->query('search'))
            ->where(function (Builder $supplierQuery) {
                $supplierQuery->whereNull('supplier_id')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('subscription_status', 'active'));
            });
    }

    /**
     * Resolve the effective page size: the legacy hard cap of 100 when no
     * `limit` is supplied, otherwise a value clamped to 1..100.
     */
    private function resolveLimit(Request $request): int
    {
        if ($request->query('limit') === null) {
            return self::DEFAULT_LIMIT;
        }

        $limit = (int) ListQuery::scalarString($request, 'limit', (string) self::DEFAULT_LIMIT);

        return min(max($limit, 1), self::DEFAULT_LIMIT);
    }

    /**
     * Resolve [column, order] against the allowlist, silently falling back to
     * the PRODUCTS default `id ASC` (not the global created_at DESC) for
     * invalid/unsafe input.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveSort(Request $request): array
    {
        return ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'asc'),
            'id',
            'asc',
        );
    }

}
