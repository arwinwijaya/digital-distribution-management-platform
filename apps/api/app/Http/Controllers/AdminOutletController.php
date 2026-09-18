<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\FinanceAuthorizationService;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOutletController extends Controller
{
    public function __construct(private readonly FinanceAuthorizationService $authorization)
    {
    }

    /**
     * Sortable columns allowlist (invalid values silently fall back to default).
     */
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'name', 'id', 'category', 'score'];

    /**
     * Admin outlet listing with filters and cursor-limit pagination (limit+1).
     *
     * Filters: category, territory_id, is_active, search (by name)
     * Pagination: limit (default 15, max 100), cursor (offset)
     * Sort: sort/order against allowlist, default created_at DESC (nulls last) + id DESC
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());

        $query = $this->applyListFilters(Outlet::query()->with('territory'), $request);

        // Cursor-limit pagination
        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        // Sort allowlist with silent fallback to created_at DESC for invalid input.
        // Reads must be scalar-safe: array params (e.g. ?sort[]=x) fall back to
        // the default instead of raising an "Array to string conversion" 500.
        [$sortColumn, $sortOrder] = ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'),
            'created_at',
            'desc',
        );

        // Aggregate queries derived from the SAME filtered builder (never count the limited rows).
        $meta = array_merge(
            [
                'limit' => $limit,
                'cursor' => $cursor,
            ],
            $this->buildListMeta(clone $query),
        );

        $rows = $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        $meta = array_merge(['has_more' => $hasMore], $meta);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Apply the list filters (category / territory_id / is_active / search) to
     * the given outlet query and return it.
     */
    private function applyListFilters(Builder $query, Request $request): Builder
    {
        // Category filter
        if ($category = $request->query('category')) {
            $query->ofCategory($category);
        }

        // Territory filter
        if (($territoryId = $request->query('territory_id')) !== null && $territoryId !== '') {
            $query->where('territory_id', (int) $territoryId);
        }

        // Active filter
        if (($isActive = $request->query('is_active')) !== null && $isActive !== '') {
            $isActiveBool = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActiveBool !== null) {
                $query->where('is_active', $isActiveBool);
            }
        }

        // Search by name
        if (($search = $request->query('search')) !== null && $search !== '') {
            $query->search($search);
        }

        return $query;
    }

    /**
     * Build the aggregate meta payload from the SAME filtered builder (never
     * counts the limited rows).
     *
     * @return array<string, mixed>
     */
    private function buildListMeta(Builder $query): array
    {
        $total = (clone $query)->count();

        $active = (clone $query)->where('is_active', true)->count();
        $inactive = (clone $query)->where('is_active', false)->count();

        return ListQuery::meta($total, [
            'active' => $active,
            'inactive' => $inactive,
        ]);
    }

    /**
     * Admin outlet creation (POST).
     *
     * Ownership (user_id) is never accepted from the client; the outlet is
     * created without a linked login identity (nullable FK), matching the
     * legacy `POST /outlets` path.
     */
    public function store(StoreAdminOutletRequest $request): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());

        $outlet = Outlet::create(array_merge($request->validated(), [
            'is_active' => $request->boolean('is_active', true),
        ]));

        return response()->json([
            'status' => 'success',
            'data' => $outlet->load('territory'),
        ], 201);
    }

    /**
     * Admin outlet update (PATCH).
     */
    public function update(UpdateOutletRequest $request, int $id): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());

        $outlet = Outlet::findOrFail($id);
        $outlet->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $outlet->fresh()->load('territory'),
        ]);
    }

    /**
     * Admin: outlet orders (paginated) with status, date, total.
     */
    public function orders(Request $request, int $outletId): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());

        $outlet = Outlet::findOrFail($outletId);

        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = max((int) $request->query('cursor', 0), 0);

        $rows = Order::query()
            ->where('outlet_id', $outlet->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit) : $rows;

        return response()->json([
            'status' => 'success',
            'data' => $data->values(),
            'meta' => [
                'limit' => $limit,
                'has_more' => $hasMore,
                'cursor' => $cursor,
            ],
        ]);
    }

    /**
     * Admin: outlet order summary aggregate.
     */
    public function summary(Request $request, int $outletId): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());

        $outlet = Outlet::findOrFail($outletId);

        $totalOrders = Order::query()->where('outlet_id', $outlet->id)->count();
        $totalAmount = (float) Order::query()->where('outlet_id', $outlet->id)->sum('total_amount');
        $lastOrder = Order::query()
            ->where('outlet_id', $outlet->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'outlet_id' => $outlet->id,
                'outlet_name' => $outlet->name,
                'total_orders' => $totalOrders,
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'total_spend' => number_format($totalAmount, 2, '.', ''),
                'last_order_date' => $lastOrder?->created_at?->toIso8601String(),
            ],
        ]);
    }
}
