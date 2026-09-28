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
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'name', 'sku', 'price', 'stock_quantity', 'id', 'category', 'status'];
    private const STOCK_HEALTH_VALUES = ['out', 'low', 'ok'];
    private const STATUS_VALUES = ['active', 'inactive', 'unpurchasable'];

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
        $query = $this->applyCatalogFilters(Product::query()->with('supplier'), $request);
        $query = $this->applyClarityFilters($query, $request);

        $limit = $this->resolveLimit($request);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        // Aggregate total derives from the SAME filtered builder (before limit/offset/order).
        // `summary` is ADDITIVE: total mirrors meta.total, out_of_stock counts the
        // same filtered set with stock_quantity <= 0 (spec "Summary strip source").
        $total = (clone $query)->count();
        $meta = [
            'limit' => $limit,
            'cursor' => $cursor,
            'total' => $total,
            'summary' => [
                'total' => $total,
                'out_of_stock' => (clone $query)->whereRaw('COALESCE(stock_quantity, 0) < 1')->count(),
            ],
            'categories' => $this->resolveCategories($query),
        ];

        // limit+1 technique: fetch one extra row to determine has_more.
        $rows = $query
            ->orderByRaw($this->resolveOrderExpression(...$this->resolveSort($request)))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        return response()->json([
            'status' => 'success',
            'data' => $data->map(fn (Product $product) => $this->serializeProduct($product)),
            'meta' => array_merge(['has_more' => $hasMore], $meta),
        ]);
    }

    /**
     * Serialize a product row with the additive nested supplier object.
     *
     * The supplier is restricted to `{id,name,subscription_status}`;
     * products with a null/orphan supplier serialize `supplier: null` and
     * never fail the list.
     *
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product): array
    {
        $attributes = $product->attributesToArray();

        $supplier = $product->getRelation('supplier');

        $attributes['supplier'] = $supplier
            ? $supplier->only(['id', 'name', 'subscription_status'])
            : null;

        return $attributes;
    }

    /**
     * Resolve the distinct, non-empty, trimmed category metadata for the
     * SAME filtered builder (before limit/offset/order).
     *
     * @return array<int, string>
     */
    private function resolveCategories(Builder $query): array
    {
        $categories = (clone $query)
            ->select('category')
            ->distinct()
            ->pluck('category');

        return $categories
            ->map(fn ($category) => is_string($category) ? trim($category) : '')
            ->filter(fn (string $category) => $category !== '')
            ->unique()
            ->sort(SORT_STRING)
            ->values()
            ->all();
    }

    /**
     * Build the safe ORDER BY expression for a requested sort.
     *
     * Category uses trimmed values and puts both NULL and empty categories
     * last. Status is a derived, stable priority: active (0), supplier
     * ineligible (1), inactive product (2). Direction reverses only the
     * derived/category value; the id DESC tie-break remains deterministic.
     */
    private function resolveOrderExpression(string $sort, string $order): string
    {
        $direction = strtoupper($order);

        if ($sort === 'category') {
            // Null/empty always last (flag ASC in both directions), then
            // case-insensitive alphabetical to mirror the frontend
            // comparator (`localeCompare` sensitivity 'base'); id DESC ties.
            $value = "COALESCE(TRIM(category), '')";

            return "({$value} = '') ASC, LOWER({$value}) {$direction}, id DESC";
        }

        if ($sort === 'status') {
            // Derived priority: Aktif (0) -> Tidak bisa dibeli (1) -> Nonaktif (2).
            // Orphan suppliers fail EXISTS and stay Aktif per spec; `NOT is_active`
            // is portable across SQLite and PostgreSQL booleans.
            $priority = "CASE\n                WHEN NOT is_active THEN 2\n                WHEN EXISTS (\n                    SELECT 1 FROM suppliers\n                    WHERE suppliers.id = products.supplier_id\n                      AND suppliers.subscription_status <> 'active'\n                ) THEN 1\n                ELSE 0\n            END";

            return "{$priority} {$direction}, id DESC";
        }

        return ListQuery::rawOrder($sort, $order);
    }

    /**
     * Apply the catalog filters (search + supplier eligibility) to the query.
     *
     * Supplier-less products stay eligible for legacy compatibility, but
     * products owned by an inactive supplier must never be exposed.
     *
     * Additive: when `include_unpurchasable=1` is present (admin context),
     * the supplier-eligibility clause is skipped entirely. The `is_active`
     * filtering is not applied by this endpoint (preserving legacy behavior).
     */
    private function applyCatalogFilters(Builder $query, Request $request): Builder
    {
        $query = $query->search($request->query('search'));

        // Admin context flag: when true, skip ONLY the supplier-eligibility clause.
        $includeUnpurchasable = $request->boolean('include_unpurchasable');

        if (! $includeUnpurchasable) {
            $query->where(function (Builder $supplierQuery) {
                $supplierQuery->whereNull('supplier_id')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('subscription_status', 'active'));
            });
        }

        return $query;
    }

    /**
     * Apply the additive Products clarity filters. Every value is normalized
     * from scalar strings and invalid values are silently ignored.
     */
    private function applyClarityFilters(Builder $query, Request $request): Builder
    {
        $category = ListQuery::scalarString($request, 'category', '');
        if ($category !== '') {
            $query->whereRaw('TRIM(category) = ?', [$category]);
        }

        $status = ListQuery::scalarString($request, 'status', '');
        if (in_array($status, self::STATUS_VALUES, true)) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } else {
                $query->whereNotNull('supplier_id')
                    ->whereHas('supplier', fn (Builder $supplier) => $supplier->where('subscription_status', '!=', 'active'));
            }
        }

        $stockHealth = ListQuery::scalarString($request, 'stock_health', '');
        if (in_array($stockHealth, self::STOCK_HEALTH_VALUES, true)) {
            // Floor semantics expressed portably (SQLite lacks FLOOR):
            //   floor(x) <= 0   <=>  x < 1
            //   1..10           <=>  x >= 1 AND x < 11
            //   floor(x) >= 11  <=>  x >= 11
            // NULL coalesces to 0.
            $stock = 'COALESCE(stock_quantity, 0)';
            match ($stockHealth) {
                'out' => $query->whereRaw($stock.' < 1'),
                'low' => $query->whereRaw($stock.' >= 1 AND '.$stock.' < 11'),
                'ok' => $query->whereRaw($stock.' >= 11'),
            };
        }

        return $query;
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
