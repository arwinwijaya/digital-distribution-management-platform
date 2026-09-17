<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Services\FinanceAuthorizationService;
use App\Services\PromotionService;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService,
        private readonly FinanceAuthorizationService $authz,
    ) {
    }

    /**
     * GET /api/admin/promotions — offset-cursor pagination (limit+1).
     *
     * Response contract: { status, data: [], meta: { has_more, limit, cursor,
     * total, summary: { total, active, scheduled, ended } } }
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = max((int) $this->scalarQueryString($request, 'cursor', '0'), 0);
        $now = Carbon::now();

        // Aggregates derive from the SAME base query (before limit/offset).
        $meta = array_merge(
            ['limit' => $limit, 'cursor' => $cursor],
            $this->buildListMeta(Promotion::query(), $now),
        );

        $rows = Promotion::query()
            ->orderByRaw(ListQuery::rawOrder('created_at', 'desc'))
            ->offset($cursor)
            ->limit($limit + 1)
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
     * Build the aggregate meta payload from the SAME base builder (never counts
     * the paginated rows). Promotions have no owner scoping: both admin and
     * owner see every promotion, so the base query is unscoped.
     *
     * @return array<string, mixed>
     */
    private function buildListMeta(Builder $query, Carbon $now): array
    {
        $total = (clone $query)->count();
        $active = (clone $query)->where('start_date', '<=', $now)->where('end_date', '>=', $now)->count();
        $scheduled = (clone $query)->where('start_date', '>', $now)->count();
        $ended = (clone $query)->where('end_date', '<', $now)->count();

        return ListQuery::meta($total, [
            'total' => $total,
            'active' => $active,
            'scheduled' => $scheduled,
            'ended' => $ended,
        ]);
    }

    /**
     * Read a query param only when it is a scalar string, otherwise return the
     * default. Guards against array input (`?cursor[]=x`) which would otherwise
     * raise an "Array to string conversion" error and yield HTTP 500.
     */
    private function scalarQueryString(Request $request, string $key, string $default): string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $promo = Promotion::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $promo,
        ]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $user  = $request->user();
        $promo = $this->promotionService->create($request->validated(), (int) $user->id);

        return response()->json([
            'status' => 'success',
            'data'   => $promo->fresh(),
        ], 201);
    }

    public function update(UpdatePromotionRequest $request, int $id): JsonResponse| \Illuminate\Http\Response
    {
        $this->assertAdminOrOwner($request);

        $promo = Promotion::findOrFail($id);
        $updated = $this->promotionService->update($promo, $request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => $updated,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $promo = Promotion::findOrFail($id);
        $this->promotionService->delete($promo);

        return response()->json([
            'status'  => 'success',
            'message' => 'Promotion deleted.',
        ]);
    }

    private function assertAdminOrOwner(Request $request): void
    {
        $user = $request->user();
        $this->authz->assertAdminOrOwner($user);
    }
}
