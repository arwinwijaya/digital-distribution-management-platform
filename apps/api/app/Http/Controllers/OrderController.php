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
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
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
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can list orders.',
            ], 403);
        }

        // Keep the legacy array response while enforcing a server-side bound.
        // Callers can request fewer rows, but never an unbounded order history.
        $limit = min(max((int) $request->query('limit', 100), 1), 100);
        $orders = Order::with(['items.product'])
            ->latest('created_at')
            ->limit($limit + 1)
            ->get();
        $hasMore = $orders->count() > $limit;
        $orders = $orders->take($limit)
            ->map(fn (Order $order) => $this->formatOrderResponse($order));

        return response()->json([
            'status' => 'success',
            'data' => $orders,
            'meta' => ['limit' => $limit, 'has_more' => $hasMore],
        ]);
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
        // lock-protected transaction.
        ConcurrencyTestBarrier::await('approval');
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
