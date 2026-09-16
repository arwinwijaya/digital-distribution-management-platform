<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Models\Promotion;
use App\Services\FinanceAuthorizationService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromotionController extends Controller
{
    public function __construct(
        private readonly PromotionService $promotionService,
        private readonly FinanceAuthorizationService $authz,
    ) {
    }

    /**
     * GET /admin/promotions — cursor pagination: limit+1.
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $limit  = $request->integer('limit', 15);
        $cursor = $request->integer('cursor'); // cursor is last-seen id
        $query  = Promotion::query()->orderBy('id');

        if ($cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        /** @var \Illuminate\Support\Collection<int, Promotion> $rows */
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data    = $rows->take($limit)->values();

        return response()->json([
            'status'  => 'success',
            'data'    => $data,
            'has_more'=> $hasMore,
            'next_cursor' => $hasMore ? $data->last()?->id : null,
        ]);
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
