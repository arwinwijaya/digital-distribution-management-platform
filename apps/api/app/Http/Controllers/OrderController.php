<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Create a new order (idempotent via idempotency_key).
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $outlet = $request->user()->outlet;

        // Check idempotency: if an idempotency_key is provided and already exists,
        // return the existing order (200) instead of creating a duplicate.
        if (!empty($validated['idempotency_key'])) {
            $existingOrder = Order::where('idempotency_key', $validated['idempotency_key'])
                ->where('outlet_id', $outlet->id)
                ->first();

            if ($existingOrder) {
                return response()->json([
                    'status' => 'success',
                    'data' => $this->formatOrderResponse($existingOrder),
                ], 200);
            }
        }

        // Create the order atomically with items
        $order = DB::transaction(function () use ($validated, $outlet) {
            // Fetch products and validate stock
            $productIds = collect($validated['items'])->pluck('product_id')->unique();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $orderId = Order::generateUniqueOrderId();
            $totalAmount = 0;

            // Calculate total and validate products
            foreach ($validated['items'] as &$item) {
                $product = $products->get($item['product_id']);
                $item['unit_price'] = $product->price;
                $item['subtotal'] = $product->price * $item['quantity'];
                $totalAmount += $item['subtotal'];
            }
            unset($item);

            // Create order
            $order = Order::create([
                'order_id' => $orderId,
                'outlet_id' => $outlet->id,
                'status' => 'New',
                'total_amount' => $totalAmount,
                'idempotency_key' => $validated['idempotency_key'] ?? null,
            ]);

            // Create order items
            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            // Record initial status in history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'New',
                'notes' => 'Order created',
            ]);

            return $order;
        });

        // Load relationships for response
        $order->load('items.product');

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrderResponse($order),
        ], 201);
    }

    /**
     * Get a single order with status history.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'statusHistory'])
            ->where('outlet_id', $request->user()->outlet->id)
            ->findOrFail($id);

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

        // Only admins can approve orders
        if (!$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can approve orders.',
            ], 403);
        }

        $order = Order::with('items.product')->findOrFail($id);

        // Only New orders can be approved
        if ($order->status !== 'New') {
            return response()->json([
                'status' => 'error',
                'message' => "Cannot approve order with status '{$order->status}'. Only orders with status 'New' can be approved.",
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->recordStatus('Confirmed', 'Order approved by admin');
        });

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
