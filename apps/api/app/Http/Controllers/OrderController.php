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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Sortable columns allowlist (invalid values silently fall back to default).
     */
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
     * List orders for administrators. Admins are not required to have an
     * outlet; outlet users remain scoped to their own outlet in show().
     *
     * Pagination: limit (default 100, max 100), cursor (offset).
     * Default order: created_at DESC (nulls last) + id DESC tiebreak.
     * Response contract: { status, data: [], meta: { has_more, limit, cursor, total } }
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can list orders.',
            ], 403);
        }

        // Validate the additive filter contract BEFORE any query construction
        // so an invalid request can never fall back to a broadened/unfiltered query.
        $filters = $this->resolveOrderFilters($request);
        if ($filters['errors'] !== []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $filters['errors'],
            ], 422);
        }

        // Keep the legacy array response while enforcing a server-side bound.
        // Callers can request fewer rows, but never an unbounded order history.
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        $query = Order::query()->with(['items.product']);

        // Apply additive filters. Each present filter narrows the builder;
        // absent filters leave the query unchanged so existing unfiltered
        // behaviour is preserved byte-for-byte.
        $this->applyOrderFilters($query, $filters['filters']);

        // Sort allowlist with silent fallback to created_at DESC for invalid input.
        // Reads are scalar-safe: array params (e.g. ?sort[]=x) fall back to the
        // default instead of raising an "Array to string conversion" 500.
        [$sortColumn, $sortOrder] = ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'),
            'created_at',
            'desc',
        );

        // Aggregate total derives from the SAME filtered builder (before
        // limit/offset/order) — never count the paginated rows.
        $meta = array_merge(
            ['limit' => $limit, 'cursor' => $cursor],
            $this->buildListMeta(clone $query),
        );

        // limit+1 technique: fetch one extra row to determine has_more.
        $orders = $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $orders->count() > $limit;
        $orders = $orders->take($limit)
            ->map(fn (Order $order) => $this->formatOrderResponse($order));

        return response()->json([
            'status' => 'success',
            'data' => $orders,
            'meta' => array_merge(['has_more' => $hasMore], $meta),
        ]);
    }

    /**
     * Allowed query keys for the admin orders endpoint: existing pagination/
     * sort keys plus the four new additive filter keys. Any key outside this
     * set is rejected with a field-level 422 error.
     *
     * @var array<string>
     */
    private const ALLOWED_QUERY_KEYS = [
        'limit',
        'cursor',
        'sort',
        'order',
        'outlet_id',
        'status',
        'start',
        'end',
    ];

    /**
     * Canonical statuses that may appear in the status CSV filter.
     *
     * @var array<string>
     */
    private const CANONICAL_STATUSES = ['New', 'Confirmed', 'Delivered', 'Partially Paid'];

    /**
     * Maximum inclusive date range for start/end filters (in days).
     */
    private const MAX_FILTER_RANGE_DAYS = 90;

    /**
     * Validate and normalize the additive order filters.
     *
     * Returns an array with 'errors' (field => message) and 'filters' (the
     * parsed values). When 'errors' is non-empty the request is invalid and
     * the endpoint must return a 422 envelope — no query is built.
     *
     * @return array{errors: array<string,string>, filters: array<string,mixed>}
     */
    private function resolveOrderFilters(Request $request): array
    {
        $errors = [];
        $filters = [];

        // Reject any unknown query keys.
        foreach (array_keys($request->query()) as $key) {
            if (! in_array($key, self::ALLOWED_QUERY_KEYS, true)) {
                $errors[$key] = 'Unknown query parameter';
            }
        }

        // outlet_id: must be a positive integer when present.
        if ($request->query('outlet_id') !== null) {
            $raw = ListQuery::scalarString($request, 'outlet_id', '');
            if (! preg_match('/^[1-9][0-9]*$/', $raw)) {
                $errors['outlet_id'] = 'Must be a positive integer';
            } else {
                $filters['outlet_id'] = (int) $raw;
            }
        }

        // status: CSV of canonical statuses; duplicates normalized; each value exact match.
        if ($request->query('status') !== null) {
            $raw = ListQuery::scalarString($request, 'status', '');
            $statuses = $this->parseStatuses($raw);
            if ($statuses === null) {
                $errors['status'] = 'Status must be one of: New,Confirmed,Delivered,Partially Paid';
            } else {
                $filters['statuses'] = $statuses;
            }
        }

        // start/end: valid YYYY-MM-DD, start <= end, inclusive range <= 90 days.
        $start = $request->query('start') !== null ? ListQuery::scalarString($request, 'start', '') : null;
        $end = $request->query('end') !== null ? ListQuery::scalarString($request, 'end', '') : null;

        $startDate = $start !== null ? $this->parseDate($start) : null;
        $endDate = $end !== null ? $this->parseDate($end) : null;

        if ($start !== null && $startDate === false) {
            $errors['start'] = 'Must be a valid date in YYYY-MM-DD format';
        }
        if ($end !== null && $endDate === false) {
            $errors['end'] = 'Must be a valid date in YYYY-MM-DD format';
        }

        if (is_string($startDate) && is_string($endDate)) {
            if ($startDate > $endDate) {
                $errors['start'] = 'Start date must be before or equal to end date';
            } else {
                $diffDays = (new \DateTimeImmutable($startDate))->diff(new \DateTimeImmutable($endDate))->days;
                if ($diffDays + 1 > self::MAX_FILTER_RANGE_DAYS) {
                    $errors['end'] = 'Date range must not exceed 90 days';
                }
            }
        }

        if (is_string($startDate)) $filters['start'] = $startDate;
        if (is_string($endDate)) $filters['end'] = $endDate;

        return ['errors' => $errors, 'filters' => $filters];
    }

    /**
     * Parse a comma-separated status string into a unique array of canonical
     * statuses. Returns null if the string is empty or contains any value
     * outside the canonical set.
     */
    private function parseStatuses(string $raw): ?array
    {
        $parts = explode(',', $raw);

        if ($parts === [] || in_array('', $parts, true)) {
            return null;
        }

        foreach ($parts as $part) {
            if (! in_array($part, self::CANONICAL_STATUSES, true)) {
                return null;
            }
        }

        return array_values(array_unique($parts));
    }

    /**
     * Parse a YYYY-MM-DD string into a validated DateTimeImmutable date string.
     * Returns false if the format is invalid or the date does not exist.
     */
    private function parseDate(string $value): string|bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = explode('-', $value);
        if (! checkdate((int) $m, (int) $d, (int) $y)) {
            return false;
        }
        return $value;
    }

    /**
     * Apply the additive order filters to the builder.
     *
     * @param  array{outlet_id?:int,statuses?:array<string>,start?:string,end?:string}  $filters
     */
    private function applyOrderFilters(Builder $query, array $filters): void
    {
        if (isset($filters['outlet_id'])) {
            $query->where('outlet_id', $filters['outlet_id']);
        }

        if (isset($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        if (isset($filters['start'])) {
            $query->where('created_at', '>=', $filters['start'].' 00:00:00');
        }

        if (isset($filters['end'])) {
            $query->where('created_at', '<=', $filters['end'].' 23:59:59');
        }
    }

    /**
     * Build the aggregate meta payload from the SAME unfiltered builder (never
     * counts the limited rows).
     *
     * @return array<string, mixed>
     */
    private function buildListMeta(Builder $query): array
    {
        return ListQuery::meta((clone $query)->count());
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
        $data = [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'outlet_id' => $order->outlet_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'paid_amount' => $order->paid_amount,
            'outstanding_balance' => number_format(max(0, ((float) $order->total_amount) - ((float) $order->paid_amount)), 2, '.', ''),
            'promotion_id' => $order->promotion_id,
            'discount_amount' => $order->discount_amount,
            'commission_percentage' => $order->commission_percentage,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        if ($includeHistory) {
            $data['status_history'] = $order->statusHistory->map(function ($history) {
                return [
                    'status' => $history->status,
                    'notes' => $history->notes,
                    'created_at' => $history->created_at,
                ];
            });
        }

        if ($order->relationLoaded('invoice') && $order->invoice) {
            $data['invoice'] = app(InvoiceService::class)->format($order->invoice);
        }

        return $data;
    }
}
