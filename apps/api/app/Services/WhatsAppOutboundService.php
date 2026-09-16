<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\WhatsAppMessage;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class WhatsAppOutboundService
{
    public function __construct(private readonly WhatsAppClient $client) {}

    public function notifyConfirmedOrder(Order $order): ?WhatsAppMessage
    {
        if ($order->status !== 'Confirmed') {
            throw new ConflictHttpException("Cannot notify order with status '{$order->status}'. Only Confirmed orders can be notified.");
        }
        if (! $order->outlet || ! config('whatsapp.enabled')) {
            return null;
        }

        $logicalKey = "order-confirmation:{$order->id}";
        $message = DB::transaction(function () use ($order, $logicalKey): WhatsAppMessage {
            // Test-only race point: both notification workers reach the
            // conflict-safe insert together when the PG suite opts in.
            if (config('whatsapp.concurrency_barrier_enabled', false)) {
                ConcurrencyTestBarrier::await('whatsapp-outbound');
            }

            // ON CONFLICT DO NOTHING keeps the transaction usable on
            // PostgreSQL when another request creates this logical message.
            DB::table('whatsapp_messages')->insertOrIgnore([
                'logical_key' => $logicalKey,
                'provider_idempotency_key' => hash('sha256', $logicalKey),
                'direction' => 'outbound',
                'phone' => $order->outlet->phone,
                'message_type' => 'order_confirmation',
                'body' => "Order {$order->order_id} is confirmed.",
                'status' => 'pending',
                'outlet_id' => $order->outlet_id,
                'order_id' => $order->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return WhatsAppMessage::where('logical_key', $logicalKey)
                ->lockForUpdate()
                ->firstOrFail();
        });

        if ($message->status === 'sent') {
            return $message->fresh();
        }

        return $this->deliver($message);
    }

    /**
     * Broadcast a promotion to eligible outlets via WhatsApp (F6).
     *
     * Targeting: outlets with >= 1 order in the last 30 days, excluding
     * outlets whose recent orders referenced products that are not
     * purchasable (inactive product or inactive supplier, via
     * Product::scopePurchasable).
     *
     * Idempotency reconciliation:
     * The F6 spec names a single logical_key ("promo-broadcast:{promo_id}"),
     * but one key can only guard one row while N outlet messages need N rows.
     * Each outlet message therefore gets a per-outlet key
     * ("promo-broadcast:{promo_id}:{outlet_id}") for row-level dedup via
     * insertOrIgnore, while the promo's broadcast_at timestamp is the overall
     * already-sent marker. Re-calling after broadcast_at is set never creates
     * duplicates; it only re-delivers rows stuck at failed.
     *
     * @return array{created: int, already_sent: bool, sent: int, failed: int}
     */
    public function broadcastPromotion(Promotion $promo): array
    {
        $promo = $promo->fresh() ?? $promo;

        if ($promo->broadcast_at !== null) {
            return $this->retryFailedBroadcast($promo);
        }

        $outlets = $this->broadcastEligibleOutlets();
        $keys = [];
        foreach ($outlets as $outlet) {
            $keys[$outlet->id] = "promo-broadcast:{$promo->id}:{$outlet->id}";
        }

        $existing = WhatsAppMessage::whereIn('logical_key', array_values($keys))
            ->pluck('logical_key')
            ->all();
        $existingLookup = array_flip($existing);

        $rows = [];
        foreach ($outlets as $outlet) {
            if (isset($existingLookup[$keys[$outlet->id]])) {
                continue;
            }
            $logicalKey = $keys[$outlet->id];
            $rows[] = [
                'logical_key' => $logicalKey,
                'provider_idempotency_key' => hash('sha256', $logicalKey),
                'direction' => 'outbound',
                'phone' => $outlet->phone,
                'message_type' => WhatsAppMessage::MESSAGE_TYPE_PROMO_BROADCAST,
                'body' => $this->formatPromotionBroadcastBody($promo, $outlet),
                'payload' => json_encode(['promotion_id' => $promo->id], JSON_THROW_ON_ERROR),
                'status' => 'pending',
                'outlet_id' => $outlet->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                if ($chunk !== []) {
                    DB::table('whatsapp_messages')->insertOrIgnore($chunk);
                }
            }
        });

        $messages = WhatsAppMessage::whereIn('logical_key', array_values($keys))->get();

        foreach ($messages as $message) {
            if ($message->status === 'sent') {
                continue;
            }
            $this->retryMessage($message);
        }

        $fresh = WhatsAppMessage::whereIn('logical_key', array_values($keys))->get();

        // Freeze the promo once at least one broadcast row exists so later
        // calls take the already_sent + retry-failed path. A run with zero
        // eligible outlets leaves broadcast_at null so a later broadcast can
        // still pick up newly eligible outlets.
        if ($fresh->isNotEmpty()) {
            $promo->markAsBroadcast();
        }

        return [
            'created' => $fresh->count() - count($existing),
            'already_sent' => false,
            'sent' => $fresh->where('status', 'sent')->count(),
            'failed' => $fresh->where('status', 'failed')->count(),
        ];
    }

    /**
     * Re-deliver only the failed rows of an already-broadcast promo.
     * Never creates new rows.
     *
     * @return array{created: int, already_sent: bool, sent: int, failed: int}
     */
    private function retryFailedBroadcast(Promotion $promo): array
    {
        $failed = WhatsAppMessage::where('message_type', WhatsAppMessage::MESSAGE_TYPE_PROMO_BROADCAST)
            ->where('status', 'failed')
            ->where('logical_key', 'like', "promo-broadcast:{$promo->id}:%")
            ->get();

        $resent = 0;
        foreach ($failed as $message) {
            $result = $this->retryMessage($message);
            if ($result->status === 'sent') {
                $resent++;
            }
        }

        $remaining = WhatsAppMessage::where('message_type', WhatsAppMessage::MESSAGE_TYPE_PROMO_BROADCAST)
            ->where('status', 'failed')
            ->where('logical_key', 'like', "promo-broadcast:{$promo->id}:%")
            ->count();

        return [
            'created' => 0,
            'already_sent' => true,
            'sent' => $resent,
            'failed' => $remaining,
        ];
    }

    /**
     * Outlets with >= 1 order in the last 30 days, minus outlets whose
     * recent orders referenced non-purchasable products.
     *
     * @return \Illuminate\Support\Collection<int, Outlet>
     */
    private function broadcastEligibleOutlets()
    {
        $since = now()->subDays(30);

        $recentOutletIds = Order::query()
            ->where('orders.created_at', '>=', $since)
            ->distinct()
            ->pluck('orders.outlet_id');

        if ($recentOutletIds->isEmpty()) {
            return collect();
        }

        $purchasableIds = Product::purchasable()->pluck('products.id');

        $taintedOutletIds = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereNotIn('order_items.product_id', $purchasableIds)
            ->distinct()
            ->pluck('orders.outlet_id');

        return Outlet::query()
            ->whereIn('id', $recentOutletIds)
            ->whereNotIn('id', $taintedOutletIds)
            ->orderBy('id')
            ->get();
    }

    private function formatPromotionBroadcastBody(Promotion $promo, Outlet $outlet): string
    {
        $discountLabel = $promo->discount_type === 'percentage'
            ? "{$promo->discount_value}% ({$promo->discount_type})"
            : "Rp {$promo->discount_value} ({$promo->discount_type})";

        $start = $promo->start_date instanceof \DateTimeInterface
            ? $promo->start_date->format('Y-m-d')
            : (string) $promo->start_date;
        $end = $promo->end_date instanceof \DateTimeInterface
            ? $promo->end_date->format('Y-m-d')
            : (string) $promo->end_date;

        return "Hi {$outlet->name}! New promotion \"{$promo->name}\" is live: "
            . "discount {$discountLabel}, valid {$start} to {$end}, "
            . "minimum order Rp {$promo->min_order}. Happy selling!";
    }

    public function retryMessage(WhatsAppMessage $message): WhatsAppMessage
    {
        if (! config('whatsapp.enabled') || $message->direction !== 'outbound' || $message->status === 'sent') {
            return $message;
        }

        return $this->deliver($message);
    }

    /**
     * Send an invoice reminder text through the provider, reusing the
     * reminder's stable idempotency key so retries never double-send.
     *
     * @return array<string, mixed>
     */
    public function sendInvoiceReminder(string $phone, string $body, string $idempotencyKey): array
    {
        // Reminder delivery has no unkeyed fallback: the contract requires the
        // provider identity so every retry is deduplicated at the boundary.
        return $this->client->sendTextWithIdempotency($phone, $body, $idempotencyKey);
    }

    /** @return array{message: WhatsAppMessage, status: string} */
    public function shareCatalog(Outlet $outlet): array
    {
        if (! config('whatsapp.enabled')) {
            $message = WhatsAppMessage::create([
                'direction' => 'outbound',
                'phone' => $outlet->phone,
                'message_type' => 'catalog',
                'status' => 'disabled',
                'outlet_id' => $outlet->id,
            ]);

            return ['message' => $message, 'status' => 'disabled'];
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->orderBy('name')
            ->limit(max(1, (int) config('whatsapp.catalog_limit', 20)))
            ->get(['name', 'sku', 'price', 'stock_quantity'])
            ->map(fn (Product $product) => [
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) $product->price,
                'stock_quantity' => $product->stock_quantity,
            ])->values()->all();

        $message = WhatsAppMessage::create([
            'direction' => 'outbound',
            'phone' => $outlet->phone,
            'message_type' => 'catalog',
            'body' => json_encode($products, JSON_THROW_ON_ERROR),
            'payload' => ['catalog' => $products],
            'status' => 'pending',
            'outlet_id' => $outlet->id,
        ]);

        return $this->deliver($message, $products);
    }

    /** @param array<int, array<string, mixed>>|null $catalog @return array{message: WhatsAppMessage, status: string} */
    private function deliver(WhatsAppMessage $message, ?array $catalog = null): array|WhatsAppMessage
    {
        $claimed = $this->claimForDelivery($message);
        if (! $claimed) {
            return $this->deliveryResult($message, $catalog);
        }

        try {
            $response = $this->dispatchDelivery($claimed, $catalog);
            $this->completeDelivery($claimed, $response);
        } catch (Throwable $exception) {
            $this->failDelivery($claimed, $exception);
        }

        return $this->deliveryResult($claimed, $catalog);
    }

    private function claimForDelivery(WhatsAppMessage $message): ?WhatsAppMessage
    {
        return DB::transaction(function () use ($message): ?WhatsAppMessage {
            $locked = WhatsAppMessage::lockForUpdate()->findOrFail($message->id);
            if ($locked->status === 'sent') {
                return null;
            }
            if ($locked->status === 'sending'
                && $locked->claimed_at !== null
                && $locked->claimed_at->gt(now()->subSeconds($this->sendLeaseSeconds()))) {
                // A live worker still owns this lease. Do not issue a second
                // provider request merely because another request arrived.
                return null;
            }
            $locked->update(['status' => 'sending', 'claimed_at' => now()]);

            return $locked;
        });
    }

    /** @param array<int, array<string, mixed>>|null $catalog @return array<string, mixed> */
    private function dispatchDelivery(WhatsAppMessage $message, ?array $catalog): array
    {
        return $message->message_type === 'catalog'
            ? $this->client->sendCatalog($message->phone, $catalog ?? ($message->payload['catalog'] ?? []))
            : $this->sendText($message->phone, (string) $message->body, (string) $message->provider_idempotency_key);
    }

    /** @param array<string, mixed> $response */
    private function completeDelivery(WhatsAppMessage $message, array $response): void
    {
        $message->update([
            'provider_message_id' => $this->providerIdFromResponse($response),
            'status' => 'sent',
            'attempts' => $message->attempts + 1,
            'error' => null,
            'claimed_at' => null,
            'sent_at' => now(),
        ]);
    }

    private function failDelivery(WhatsAppMessage $message, Throwable $exception): void
    {
        $message->update([
            'status' => 'failed',
            'attempts' => $message->attempts + 1,
            'claimed_at' => null,
            'error' => $exception->getMessage(),
        ]);
    }

    /** @param array<int, array<string, mixed>>|null $catalog */
    private function deliveryResult(WhatsAppMessage $message, ?array $catalog): array|WhatsAppMessage
    {
        $fresh = $message->fresh();

        return is_array($catalog) ? ['message' => $fresh, 'status' => $fresh->status] : $fresh;
    }

    private function sendLeaseSeconds(): int
    {
        return max(1, (int) config('whatsapp.send_lease_seconds', 300));
    }

    /** @return array<string, mixed> */
    private function sendText(string $phone, string $body, string $idempotencyKey): array
    {
        return $this->client->sendTextWithIdempotency($phone, $body, $idempotencyKey);
    }

    /** @param array<string, mixed> $response */
    private function providerIdFromResponse(array $response): ?string
    {
        foreach (['provider_message_id', 'message_id', 'id'] as $key) {
            if (is_scalar($response[$key] ?? null) && trim((string) $response[$key]) !== '') {
                return trim((string) $response[$key]);
            }
        }

        return data_get($response, 'messages.0.id');
    }
}
