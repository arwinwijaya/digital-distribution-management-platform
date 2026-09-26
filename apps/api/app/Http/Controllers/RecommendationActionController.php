<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecommendationActionRequest;
use App\Models\RecommendationAction;
use App\Services\RecommendationActionService;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecommendationActionController extends Controller
{
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'id', 'status', 'type'];

    public function __construct(
        private readonly RecommendationActionService $service,
    ) {}

    /**
     * GET /api/admin/recommendation-actions — offset-cursor pagination (limit+1).
     *
     * Response contract: { status, data: [], meta: { has_more, limit, cursor, total } }
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters(RecommendationAction::query(), $request);

        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        [$sortColumn, $sortOrder] = ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'),
            'created_at',
            'desc',
        );

        $meta = array_merge(
            ['limit' => $limit, 'cursor' => $cursor],
            $this->buildListMeta(clone $query),
        );

        $rows = $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        return response()->json([
            'status' => 'success',
            'data'   => $data->map(fn ($action) => $this->formatAction($action))->all(),
            'meta'   => array_merge(['has_more' => $hasMore], $meta),
        ]);
    }

    /**
     * POST /api/admin/recommendation-actions — create a draft action.
     *
     * Actor is always derived from the authenticated session, never from payload.
     * Idempotency key is provided by the client and passed through to the service.
     */
    public function store(StoreRecommendationActionRequest $request): JsonResponse
    {
        $actor = $request->user();
        $validated = $request->validated();
        $idempotencyKey = $validated['idempotency_key'];

        try {
            $result = $this->service->createDraft($validated, $actor, $idempotencyKey);
        } catch (ValidationException $e) {
            // Idempotency conflict (same key, different payload) -> 409.
            if (isset($e->errors()['idempotency_key'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->errors()['idempotency_key'][0] ?? 'Idempotency conflict.',
                ], 409);
            }

            throw $e; // Other validation errors -> 422 via Handler.
        }

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatAction($result['action'], $result['created']),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * POST /api/admin/recommendation-actions/{id}/approve — transition draft -> approved.
     *
     * Actor is always the authenticated session user; any `approved_by` field in
     * the request body is ignored.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $result = $this->service->approve($id, $request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatAction($result['action'], ! $result['replay']),
        ]);
    }

    /**
     * POST /api/admin/recommendation-actions/{id}/reject — transition draft -> rejected.
     *
     * The rejection reason is required; actor comes from the session only.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $reason = trim((string) $request->input('reason', ''));

        $result = $this->service->reject($id, $request->user(), $reason);

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatAction($result['action'], ! $result['replay']),
        ]);
    }

    /**
     * POST /api/admin/recommendation-actions/{id}/execute — approval-gated execution.
     *
     * Only `approved` actions run. `draft_order` delegates to OrderCreationService.
     * Actor comes from the session only.
     */
    public function execute(Request $request, int $id): JsonResponse
    {
        $result = $this->service->execute($id, $request->user());

        return response()->json([
            'status' => 'success',
            'data'   => $this->formatAction($result['action'], ! $result['replay']),
        ]);
    }

    /**
     * Apply common list filters.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if (($status = $request->query('status')) !== null && $status !== '') {
            $query->where('status', $status);
        }

        if (($type = $request->query('type')) !== null && $type !== '') {
            $query->where('type', $type);
        }

        if (($outletId = $request->query('outlet_id')) !== null && $outletId !== '') {
            $query->where('outlet_id', (int) $outletId);
        }

        return $query;
    }

    /**
     * Build the aggregate meta payload from the SAME filtered builder.
     */
    private function buildListMeta(Builder $query): array
    {
        return ListQuery::meta((clone $query)->count());
    }

    /**
     * Format a RecommendationAction for API response.
     */
    private function formatAction(RecommendationAction $action, bool $created = true): array
    {
        return [
            'id'                    => $action->id,
            'source_event_id'       => $action->source_event_id,
            'outlet_id'             => $action->outlet_id,
            'created_by'            => $action->created_by,
            'approved_by'           => $action->approved_by,
            'executed_by'           => $action->executed_by,
            'type'                  => $action->type,
            'status'                => $action->status,
            'payload'               => $action->payload,
            'idempotency_key'       => $action->idempotency_key,
            'idempotency_payload_hash' => $action->idempotency_payload_hash,
            'approved_at'           => $action->approved_at,
            'executed_at'           => $action->executed_at,
            'rejection_reason'      => $action->rejection_reason,
            'execution_result'      => $action->execution_result,
            'method'                => $action->method,
            'method_version'        => $action->method_version,
            'fallback'              => $action->fallback,
            'data_sufficiency'      => $action->data_sufficiency,
            'metadata'              => $action->metadata,
            'idempotent_replay'     => ! $created,
            'created_at'            => $action->created_at,
            'updated_at'            => $action->updated_at,
        ];
    }
}