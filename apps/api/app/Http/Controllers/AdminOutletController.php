<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOutletRequest;
use App\Models\Order;
use App\Models\Outlet;
use App\Services\FinanceAuthorizationService;
use App\Support\ListQuery;
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

        $query = Outlet::query()->with('territory');

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

        // Cursor-limit pagination
        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = max((int) $request->query('cursor', 0), 0);

        // Aggregate queries derived from the SAME filtered builder (never count the limited rows).
        $total = (clone $query)->count();

        $active = (clone $query)->where('is_active', true)->count();
        $inactive = (clone $query)->where('is_active', false)->count();

        // Sort allowlist with silent fallback to created_at DESC for invalid input.
        [$sortColumn, $sortOrder] = ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            (string) $request->query('sort', ''),
            (string) $request->query('order', 'desc'),
            'created_at',
            'desc',
        );

        $rows = $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        $meta = array_merge(
            [
                'has_more' => $hasMore,
                'limit' => $limit,
                'cursor' => $cursor,
            ],
            ListQuery::meta($total, [
                'active' => $active,
                'inactive' => $inactive,
            ]),
        );

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
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
