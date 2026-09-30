<?php

namespace App\Http\Controllers;

use App\Models\ReplenishmentPlan;
use App\Services\ReplenishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReplenishmentPlanController extends Controller
{
    public function __construct(
        private readonly ReplenishmentService $service,
    ) {}

    /**
     * POST /api/admin/replenishment-plans/{id}/approve
     *
     * Approves a draft replenishment plan. Actor is always the authenticated user.
     * Idempotent: replay returns the same result without duplicate audit.
     * Only admins with supply_chain:edit can call.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $result = $this->service->approve($id, $user);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatPlan($result['plan'], $result['replay']),
        ]);
    }

    /**
     * POST /api/admin/replenishment-plans/{id}/execute
     *
     * Executes an approved plan, recording draft PO metadata.
     * Requires a logical_key for idempotency.
     * Only admins with supply_chain:edit can call.
     * Divergent payload with same logical_key returns 409.
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'logical_key' => 'required|string|max:255',
        ])->validate();

        $user = $request->user();
        try {
            $result = $this->service->execute($id, $user, $validated);
        } catch (ValidationException $e) {
            // Idempotency conflict (same key, different payload) -> 409.
            if (isset($e->errors()['logical_key'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->errors()['logical_key'][0] ?? 'Idempotency conflict.',
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatPlan($result['plan'], $result['replay']),
        ]);
    }

    /**
     * Format a replenishment plan for API response.
     */
    private function formatPlan(ReplenishmentPlan $plan, bool $replay = false): array
    {
        return [
            'id' => $plan->id,
            'supplier_id' => $plan->supplier_id,
            'supplier' => $plan->supplier ? [
                'id' => $plan->supplier->id,
                'name' => $plan->supplier->name,
            ] : null,
            'created_by' => $plan->created_by,
            'approved_by' => $plan->approved_by,
            'executed_by' => $plan->execution_result['executed_by'] ?? null,
            'status' => $plan->status,
            'window_start' => $plan->window_start?->toDateString(),
            'window_end' => $plan->window_end?->toDateString(),
            'approved_at' => $plan->approved_at?->toIso8601String(),
            'executed_at' => $plan->executed_at?->toIso8601String(),
            'execution_result' => $plan->execution_result,
            'metadata' => $plan->metadata,
            'items' => $plan->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'price' => $item->product->price,
                ] : null,
                'reorder_quantity' => $item->reorder_quantity,
                'data_sufficiency' => $item->data_sufficiency,
                'metadata' => $item->metadata,
            ])->all(),
            'idempotent_replay' => $replay,
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
