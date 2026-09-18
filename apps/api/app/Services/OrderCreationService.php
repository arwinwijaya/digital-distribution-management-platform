<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function __construct(private readonly CreditLimitService $creditLimitService)
    {
    }

    /**
     * Create an order with an outlet-scoped idempotency identity.
     *
     * A unique-key race is retried and read back so both callers receive the
     * order created by the winner instead of an unhandled database exception.
     * Product locks and all order writes remain inside one transaction.
     *
     * @param  array{items: array<int, array{product_id: int, quantity: int}>, promotion_id?: int}  $validated
     * @return array{order: Order, created: bool}
     */
    public function create(array $validated, Outlet $outlet, string $requestIdentity, ?int $salesUserId = null): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($validated, $outlet, $requestIdentity, $salesUserId) {
                    // Serialize all credit consumption for this outlet before reading
                    // outstanding orders. This lock is held until order and stock writes commit.
                    $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();

                    // Test-only barrier: both public requests enter this transaction
                    // immediately before the idempotency/product critical section.
                    // Gated so production never pauses inside this transaction.
                    if (config('orders.concurrency_barrier_enabled', false)) {
                        ConcurrencyTestBarrier::await('order');
                    }
                    $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                    if ($existingOrder) {
                        // Same key with a different canonical payload is a 422
                        // conflict. Legacy orders predate the stored fingerprint
                        // (NULL), so they fall back to comparing the payload
                        // against the persisted items instead of reusing blindly.
                        $this->assertSamePayload($existingOrder, $validated['items']);

                        return ['order' => $existingOrder, 'created' => false];
                    }

                    // Product rows are locked and validated without mutation first, so
                    // a rejected credit check cannot consume stock or persist an order.
                    [$items, $totalAmount] = $this->prepareProducts($validated['items']);

                    // Phase 7 T5: promo applied at creation only. Resolves promotion_id
                    // from the request, validates min_order + active window, and
                    // stores promotion_id/discount_amount snapshot on the order.
                    // Credit check consumes the post-discount total.
                    $promotionId = isset($validated['promotion_id']) ? (int) $validated['promotion_id'] : null;
                    $discountAmount = 0.0;
                    if ($promotionId !== null) {
                        $promotion = Promotion::lockForUpdate()->find($promotionId);
                        if ($promotion === null) {
                            throw ValidationException::withMessages([
                                'promotion_id' => 'The selected promotion does not exist.',
                            ]);
                        }
                        $this->assertPromotionApplicable($promotion, $totalAmount, $items);
                        $discountAmount = app(PromotionService::class)
                            ->calculateDiscount($promotion, $totalAmount);
                    }
                    $payableTotal = max(0.0, $totalAmount - $discountAmount);

                    $this->creditLimitService->assertCanPlace($lockedOutlet, $this->moneyToCents($payableTotal));
                    $this->reservePreparedProducts($items);
                    $order = $this->persistOrder($lockedOutlet, $requestIdentity, $payableTotal, $items, $promotionId, $discountAmount, $salesUserId);

                    return ['order' => $order, 'created' => true];
                });
            } catch (QueryException $exception) {
                // The unique request identity is the expected conflict when
                // another request won the race. Fetch it after rollback.
                $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                if ($existingOrder) {
                    $this->assertSamePayload($existingOrder, $validated['items']);

                    return ['order' => $existingOrder, 'created' => false];
                }

                if ($attempt === 2) {
                    throw $exception;
                }

                usleep(10000 * ($attempt + 1));
            }
        }

        throw new \LogicException('Unable to create order.');
    }

    /**
     * Throw 422 when the replayed payload differs from what the order was
     * created with. NULL-fingerprint legacy rows compare against the
     * persisted line items; rows with a stored fingerprint compare hashes.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $requestedItems
     */
    private function assertSamePayload(Order $existingOrder, array $requestedItems): void
    {
        $fingerprint = self::payloadFingerprint($requestedItems);

        if ($existingOrder->idempotency_payload_hash !== null) {
            // Timing-safe compare keeps fingerprint probing constant-time.
            if (! hash_equals($existingOrder->idempotency_payload_hash, $fingerprint)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This order request identity was already used with a different payload.',
                ]);
            }

            return;
        }

        // Legacy orders have no stored fingerprint: compare the replayed
        // canonical payload against the original persisted items.
        $existingOrder->loadMissing('items');
        $original = $existingOrder->items
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
            ])
            ->sortBy('product_id')
            ->values()
            ->all();
        $replayed = collect($requestedItems)
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ])
            ->sortBy('product_id')
            ->values()
            ->all();

        if ($original !== $replayed) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This order request identity was already used with a different payload.',
            ]);
        }
    }

    /**
     * Canonical SHA-256 fingerprint of the ordered line items. Quantity-only
     * or item-set differences change the fingerprint; key order and duplicate
     * request envelopes do not.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $requestedItems
     */
    public static function payloadFingerprint(array $requestedItems): string
    {
        $canonical = collect($requestedItems)
            ->map(fn ($item) => [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ])
            ->sortBy('product_id')
            ->values()
            ->all();

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    /**
     * Lock products in a stable order, validate availability, and reserve stock.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $requestedItems
     * @return array{0: array<int, array<string, int|float>>, 1: float}
     */
    private function prepareProducts(array $requestedItems): array
    {
        $productIds = collect($requestedItems)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id);
        $products = Product::whereIn('id', $productIds)
            ->with('supplier')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $totalAmount = 0.0;
        $items = [];

        foreach ($requestedItems as $index => $item) {
            $product = $products->get((int) $item['product_id']);
            if (!$product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'The referenced product does not exist.',
                ]);
            }
            if (! $product->isPurchasable()) {
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
            $quantity = (int) $item['quantity'];
            $subtotal = $unitPrice * $quantity;
            $totalAmount += $subtotal;
            $items[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return [$items, $totalAmount];
    }

    /** @param array<int, array<string, int|float>> $items */
    private function reservePreparedProducts(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::lockForUpdate()->findOrFail($item['product_id']);
            $product->stock_quantity -= (int) $item['quantity'];
            $product->save();
        }
    }

    private function moneyToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Phase 7 T5: a promo may only be applied at creation when it is active,
     * today falls inside its inclusive date range, and the pre-discount
     * subtotal meets min_order. The promo's product_id (when set) must also
     * be present in the order.
     *
     * @param  array<int, array<string, int|float>>  $items
     */
    private function assertPromotionApplicable(Promotion $promotion, float $subtotal, array $items): void
    {
        if (! $promotion->is_active) {
            throw ValidationException::withMessages([
                'promotion_id' => 'The selected promotion is not active.',
            ]);
        }

        $today = now()->startOfDay();
        if ($today->lt($promotion->start_date) || $today->gt($promotion->end_date)) {
            throw ValidationException::withMessages([
                'promotion_id' => 'The selected promotion is not valid today.',
            ]);
        }

        if ((float) $promotion->min_order > 0 && $subtotal < (float) $promotion->min_order) {
            throw ValidationException::withMessages([
                'promotion_id' => 'Order does not meet the promotion minimum order amount.',
            ]);
        }

        if ($promotion->product_id !== null) {
            $orderedProductIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id);
            if (! $orderedProductIds->contains((int) $promotion->product_id)) {
                throw ValidationException::withMessages([
                    'promotion_id' => 'The selected promotion does not apply to any ordered product.',
                ]);
            }
        }
    }

    /**
     * Persist the order, line items, and initial history atomically.
     *
     * @param  array<int, array<string, int|float>>  $items
     */
    private function persistOrder(
        Outlet $outlet,
        string $requestIdentity,
        float $totalAmount,
        array $items,
        ?int $promotionId = null,
        float $discountAmount = 0.0,
        ?int $salesUserId = null,
    ): Order {
        $order = Order::create([
            'order_id' => Order::generateUniqueOrderId(),
            'outlet_id' => $outlet->id,
            'sales_user_id' => $salesUserId,
            'status' => 'New',
            'total_amount' => $totalAmount,
            'commission_percentage' => config('orders.commission_percentage', 2.00),
            'idempotency_key' => $requestIdentity,
            'idempotency_payload_hash' => self::payloadFingerprint($items),
            'promotion_id' => $promotionId,
            'discount_amount' => $discountAmount,
        ]);

        foreach ($items as $item) {
            OrderItem::create(array_merge($item, ['order_id' => $order->id]));
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Order created',
        ]);

        return $order;
    }
}
