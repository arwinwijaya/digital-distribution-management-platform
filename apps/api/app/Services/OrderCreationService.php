<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
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
     * @param  array{items: array<int, array{product_id: int, quantity: int}>}  $validated
     * @return array{order: Order, created: bool}
     */
    public function create(array $validated, Outlet $outlet, string $requestIdentity): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($validated, $outlet, $requestIdentity) {
                    // Serialize all credit consumption for this outlet before reading
                    // outstanding orders. This lock is held until order and stock writes commit.
                    $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();

                    // Test-only barrier: both public requests enter this transaction
                    // immediately before the idempotency/product critical section.
                    ConcurrencyTestBarrier::await('order');
                    $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                    if ($existingOrder) {
                        return ['order' => $existingOrder, 'created' => false];
                    }

                    // Product rows are locked and validated without mutation first, so
                    // a rejected credit check cannot consume stock or persist an order.
                    [$items, $totalAmount] = $this->prepareProducts($validated['items']);
                    $this->creditLimitService->assertCanPlace($lockedOutlet, $this->moneyToCents($totalAmount));
                    $this->reservePreparedProducts($items);
                    $order = $this->persistOrder($lockedOutlet, $requestIdentity, $totalAmount, $items);

                    return ['order' => $order, 'created' => true];
                });
            } catch (QueryException $exception) {
                // The unique request identity is the expected conflict when
                // another request won the race. Fetch it after rollback.
                $existingOrder = Order::where('idempotency_key', $requestIdentity)->first();
                if ($existingOrder) {
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
     * Persist the order, line items, and initial history atomically.
     *
     * @param  array<int, array<string, int|float>>  $items
     */
    private function persistOrder(
        Outlet $outlet,
        string $requestIdentity,
        float $totalAmount,
        array $items,
    ): Order {
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

        return $order;
    }
}
