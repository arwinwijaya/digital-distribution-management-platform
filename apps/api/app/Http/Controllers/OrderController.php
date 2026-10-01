<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\FinanceAuthorizationService;
use App\Services\InvoiceService;
use App\Services\OrderCreationService;
use App\Services\WhatsAppService;
use App\Http\Requests\CancelOrderRequest;
use App\Support\ConcurrencyTestBarrier;
use App\Support\ListQuery;
use App\Support\OrderFormatter;
use App\Support\OrderListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    private const SORT_ALLOWLIST = ['created_at', 'updated_at', 'order_id', 'status', 'total_amount', 'id'];

    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly WhatsAppService $whatsappService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Create an order atomically. The effective request identity is always
     * persisted in the unique idempotency_key column: an explicit key is
     * namespaced to the outlet, while a missing key was derived from the
     * canonical payload by StoreOrderRequest.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $outlet = $request->user()->outlet;
        $requestIdentity = $this->requestIdentity($outlet->id, $validated['idempotency_key']);
        $result = $this->orderCreationService->create($validated, $outlet, $requestIdentity);
        $result['order']->load('items.product');

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrderResponse($result['order']),
        ], $result['created'] ? 201 : 200);
    }

    private function requestIdentity(int $outletId, string $clientIdentity): string
    {
        return hash('sha256', json_encode([
            'outlet_id' => $outletId,
            'request_identity' => $clientIdentity,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * List orders for administrators or the authenticated outlet. Admins are
     * not required to have an outlet; outlet lists use the token's outlet.
     *
     * Pagination: limit (default 100, max 100), cursor (offset).
     * Default order: created_at DESC (nulls last) + id DESC tiebreak.
     * Response contract: { status, data: [], meta: { has_more, limit, cursor, total } }
     */
    public function index(Request $request): JsonResponse
    {
        $guard = $this->indexGuard($request);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }

        // For outlet users, silently override any client-supplied outlet_id from the token
        // before validation so that tampering with outlet_id does not trigger 422 or IDOR leaks.
        if ($request->user()->isOutlet()) {
            $request->query->set('outlet_id', (string) $request->user()->outlet->id);
        }

        // Validate the additive filter contract BEFORE any query construction
        // so an invalid request can never fall back to a broadened/unfiltered query.
        $filters = app(OrderListFilters::class)->resolve($request);
        if ($filters['errors'] !== []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $filters['errors'],
            ], 422);
        }

        $list = $this->buildOrderList($request, $filters['filters']);

        return response()->json([
            'status' => 'success',
            'data' => $list['orders'],
            'meta' => $list['meta'],
        ]);
    }

    private function indexGuard(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isOutlet()) {
            if ($user->outlet) {
                return null;
            }

            return response()->json([
                'status' => 'error',
                'message' => 'The authenticated user is not associated with an outlet.',
            ], 403);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized. Only admins can list orders.',
        ], 403);
    }

    /** @param array<string,mixed> $filters */
    private function buildOrderList(Request $request, array $filters): array
    {
        [$limit, $cursor] = $this->listLimitAndCursor($request);
        [$sort, $direction] = $this->listSorting($request);
        $query = Order::query()->with(['items.product']);
        app(OrderListFilters::class)->apply($query, $filters);
        $meta = array_merge(['limit' => $limit, 'cursor' => $cursor], ListQuery::meta((clone $query)->count()));
        $raw = $this->fetchOrderPage($query, $sort, $direction, $limit, $cursor);

        return [
            'orders' => $raw->take($limit)->map(fn (Order $row) => $this->formatOrderResponse($row)),
            'meta' => array_merge(['has_more' => $raw->count() > $limit], $meta),
        ];
    }

    /** @return array{0:int,1:int} */
    private function listLimitAndCursor(Request $request): array
    {
        $limit = min(max((int) $request->query('limit', 100), 1), 100);

        return [$limit, ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit)];
    }

    /** @return array{0:string,1:string} */
    private function listSorting(Request $request): array
    {
        return ListQuery::resolveSort(
            self::SORT_ALLOWLIST, ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'), 'created_at', 'desc',
        );
    }

    private function fetchOrderPage(Builder $query, string $sort, string $direction, int $limit, int $cursor): mixed
    {
        return $query->orderByRaw(ListQuery::rawOrder($sort, $direction))->limit($limit + 1)->offset($cursor)->get();
    }

    /**
     * Get an order. Outlet users can only see orders belonging to their outlet;
     * admins can view any order without dereferencing an outlet relation.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = Order::with(['items.product', 'statusHistory']);
        $user = $request->user();

        if (! $user->isAdmin()) {
            $outlet = $user->outlet;
            if (! $outlet) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The authenticated user is not associated with an outlet.',
                ], 403);
            }
            $query->where('outlet_id', $outlet->id);
        }

        $order = $query->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrderResponse($order, true),
        ]);
    }

    /**
     * Approve an order (admin only, transitions New -> Confirmed).
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! app(FinanceAuthorizationService::class)->isAdmin($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can approve orders.',
            ], 403);
        }

        [$order, $approved] = $this->runApprovalTransaction($id);

        return $this->approvalResponse($order, $approved);
    }

    /** @return array{0: Order, 1: bool|null} */
    private function runApprovalTransaction(int $id): array
    {
        $result = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $result = DB::transaction(fn (): array => $this->approveInTransaction($id));
                break;
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
                usleep(10000 * ($attempt + 1));
            }
        }

        return $result;
    }

    /** @return array{0: Order, 1: bool|null} */
    private function approveInTransaction(int $id): array
    {
        // Both the status transition and invoice creation occur in this same
        // lock-protected transaction. Guarded so production never pauses inside it.
        if (config('orders.concurrency_barrier_enabled', false)) {
            ConcurrencyTestBarrier::await('approval');
        }
        $order = Order::with('items.product')->lockForUpdate()->findOrFail($id);

        if ($order->status === 'New') {
            $order->recordStatus('Confirmed', 'Order approved by admin');
            $this->invoiceService->createForApprovedOrder($order);

            return [$order, true];
        }

        if ($order->status === 'Confirmed') {
            // Approval retries reuse the immutable invoice and do not append
            // another status-history row.
            $this->invoiceService->createForApprovedOrder($order);

            return [$order, false];
        }

        return [$order, null];
    }

    private function approvalResponse(Order $order, ?bool $approved): JsonResponse
    {
        if ($approved === null) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot approve order with status '{$order->status}'. Only orders with status 'New' can be approved.",
            ], 422);
        }

        $order->load(['statusHistory', 'invoice']);
        if ($approved) {
            // Outbound provider failure is isolated and persisted by WhatsAppService;
            // it must never roll back this already-committed order transition.
            $this->whatsappService->notifyConfirmedOrder($order->load('outlet'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrderResponse($order),
        ]);
    }

    public function cancel(CancelOrderRequest $request, int $id): JsonResponse
    {
        $invoice = $this->invoiceService->cancelOrder($id);

        return response()->json([
            'status' => 'success',
            'data' => $this->invoiceService->format($invoice),
        ]);
    }

    /**
     * Format order for API response.
     */
    private function formatOrderResponse(Order $order, bool $includeHistory = false): array
    {
        return app(OrderFormatter::class)->format($order, $includeHistory);
    }
}
