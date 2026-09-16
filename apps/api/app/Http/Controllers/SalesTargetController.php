<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesTargetRequest;
use App\Http\Requests\UpdateSalesTargetRequest;
use App\Models\SalesTarget;
use App\Services\FinanceAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTargetController extends Controller
{
    public function __construct(
        private readonly FinanceAuthorizationService $authz,
    ) {
    }

    /**
     * GET /admin/sales-targets — cursor pagination: limit+1.
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $limit  = $request->integer('limit', 15);
        $cursor = $request->integer('cursor');
        $query  = SalesTarget::query()
            ->with(['user:id,name,email', 'createdBy:id,name,email'])
            ->orderBy('id');

        if ($cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('period')) {
            $query->where('period', $request->string('period'));
        }

        /** @var \Illuminate\Support\Collection<int, SalesTarget> $rows */
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data    = $rows->take($limit)->values();

        return response()->json([
            'status'      => 'success',
            'data'        => $data,
            'has_more'    => $hasMore,
            'next_cursor' => $hasMore ? $data->last()?->id : null,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $target = SalesTarget::with(['user:id,name,email', 'createdBy:id,name,email'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $target,
        ]);
    }

    public function store(StoreSalesTargetRequest $request): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $target = SalesTarget::create([
            ...$request->validated(),
            'created_by' => (int) $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $target->fresh()->load(['user:id,name,email', 'createdBy:id,name,email']),
        ], 201);
    }

    public function update(UpdateSalesTargetRequest $request, int $id): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $target = SalesTarget::findOrFail($id);
        $target->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => $target->fresh()->load(['user:id,name,email', 'createdBy:id,name,email']),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertAdminOrOwner($request);

        $target = SalesTarget::findOrFail($id);
        $target->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Sales target deleted.',
        ]);
    }

    private function assertAdminOrOwner(Request $request): void
    {
        $user = $request->user();
        $this->authz->assertAdminOrOwner($user);
    }
}
