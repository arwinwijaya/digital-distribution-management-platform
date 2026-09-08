<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
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

        // A unique value per outlet prevents the same client key from colliding
        // across outlets while retaining a single database uniqueness boundary.
        $requestIdentity = hash('sha256', json_encode([
            'outlet_id' => $outlet->id,
            'request_identity' => $validated['idempotency_key'],
        ], JSON_THROW_ON_ERROR));

        // A concurrent insert can race the initial lookup. Retrying the
        // transaction and reading the unique row makes the loser return the
        // winner's order rather than exposing a unique-constraint error.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $result = DB::transaction(function () use ($validated, $outlet, $requestIdentity) {
                    $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                    if ($existingOrder) {
                        return ['order' => $existingOrder, 'created' => false];
                    }

                    $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id) => (int) $id);
                    // Lock in a stable order so simultaneous orders cannot
                    // oversell stock (and avoid lock-order deadlocks).
                    $products = Product::whereIn('id', $productIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    $totalAmount = 0.0;
                    $items = [];
                    foreach ($validated['items'] as $index => $item) {
                        $product = $products->get((int) $item['product_id']);

                        // The exists rule handles missing products before this
                        // point; this guard also protects against a concurrent
                        // deletion or a malformed request reaching the service.
                        if (!$product) {
                            throw ValidationException::withMessages([
                                "items.{$index}.product_id" => 'The referenced product does not exist.',
                            ]);
                        }

                        if (!$product->is_active) {
                            throw ValidationException::withMessages([
                                "items.{$index}.product_id" => 'The selected product is no longer available.',
                            ]);
                        }

                        if ($product->stock_quantity < $item['quantity']) {
                            throw ValidationException::withMessages([
                                "items.{$index}.quantity" => "Insufficient stock. Only {$product->stock_quantity} unit(s) remain.",
                            ]);
                        }

                        $unitPrice = (float) $product->price;
                        $subtotal = $unitPrice * (int) $item['quantity'];
                        $totalAmount += $subtotal;
                        $items[] = [
                            'product_id' => $product->id,
                            'quantity' => (int) $item['quantity'],
                            'unit_price' => $unitPrice,
                            'subtotal' => $subtotal,
                        ];

                        // Stock is reserved as part of the same transaction as
                        // the order. Any validation or insert failure rolls it back.
                        $product->stock_quantity -= (int) $item['quantity'];
                        $product->save();
                    }

                    $order = Order::create([
                        'order_id' => Order::generateUniqueOrderId(),
                        'outlet_id' => $outlet->id,
                        'status' => 'New',
                        'total_amount' => $totalAmount,
                        'commission_percentage' => config('orders.commission_percentage', 2.00),
                        'idempotency_key' => $requestIdentity,
                    ]);

                    foreach ($items as $item) {
                        OrderItem::create(array_merge($item, ['order_id' => $order->id]));
                    }

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'status' => 'New',
                        'notes' => 'Order created',
                    ]);

                    return ['order' => $order, 'created' => true];
                });

                $order = $result['order'];
                $order->load('items.product');

                return response()->json([
                    'status' => 'success',
                    'data' => $this->formatOrderResponse($order),
                ], $result['created'] ? 201 : 200);
            } catch (QueryException $exception) {
                // The unique request identity is the expected conflict when
                // another request won the race. Fetch it after rollback.
                $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                if ($existingOrder) {
                    $existingOrder->load('items.product');

                    return response()->json([
                        'status' => 'success',
                        'data' => $this->formatOrderResponse($existingOrder),
                    ], 200);
                }

                if ($attempt === 2) {
                    throw $exception;
                }

                usleep(10000 * ($attempt + 1));
            }
        }

        // The loop always returns or throws; this is only a type-safe fallback.
        abort(500, 'Unable to create order.');
    }

    /**
     * List orders for administrators. Admins are not required to have an
     * outlet; outlet users remain scoped to their own outlet in show().
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can list orders.',
            ], 403);
        }

        $orders = Order::with(['items.product'])
            ->latest()
            ->get()
            ->map(fn (Order $order) => $this->formatOrderResponse($order));

        return response()->json([
            'status' => 'success',
            'data' => $orders,
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

        if (!$user->isAdmin()) {
            $outlet = $user->outlet;
            if (!$outlet) {
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

        if (!$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can approve orders.',
            ], 403);
        }

        $result = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $result = DB::transaction(function () use ($id) {
                    // The status check and transition are both protected by the
                    // same row lock. Exactly one concurrent approval can append history.
                    $order = Order::with('items.product')->lockForUpdate()->findOrFail($id);
                    if ($order->status !== 'New') {
                        return [$order, false];
                    }

                    $order->recordStatus('Confirmed', 'Order approved by admin');
                    return [$order, true];
                });
                break;
            } catch (QueryException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
                usleep(10000 * ($attempt + 1));
            }
        }

        [$order, $approved] = $result;

        if (!$approved) {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot approve order with status '{$order->status}'. Only orders with status 'New' can be approved.",
            ], 422);
        }

        $order->load('statusHistory');

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrderResponse($order),
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

        return $data;
    }
}
