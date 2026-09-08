<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\WhatsAppMessage;
use Illuminate\Database\QueryException;
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
            $existing = WhatsAppMessage::where('logical_key', $logicalKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            try {
                return WhatsAppMessage::create([
                    'logical_key' => $logicalKey,
                    'provider_idempotency_key' => hash('sha256', $logicalKey),
                    'direction' => 'outbound',
                    'phone' => $order->outlet->phone,
                    'message_type' => 'order_confirmation',
                    'body' => "Order {$order->order_id} is confirmed.",
                    'status' => 'pending',
                    'outlet_id' => $order->outlet_id,
                    'order_id' => $order->id,
                ]);
            } catch (QueryException) {
                return WhatsAppMessage::where('logical_key', $logicalKey)->lockForUpdate()->firstOrFail();
            }
        });

        if (in_array($message->status, ['sent', 'sending'], true)) {
            return $message->fresh();
        }

        return $this->deliver($message);
    }

    public function retryMessage(WhatsAppMessage $message): WhatsAppMessage
    {
        if (! config('whatsapp.enabled') || $message->direction !== 'outbound' || $message->status === 'sent') {
            return $message;
        }
        if ($message->status === 'sending') {
            return $message->fresh();
        }

        return $this->deliver($message);
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
        $claimed = DB::transaction(function () use ($message): ?WhatsAppMessage {
            $locked = WhatsAppMessage::lockForUpdate()->findOrFail($message->id);
            if (in_array($locked->status, ['sent', 'sending'], true)) {
                return null;
            }
            $locked->update(['status' => 'sending']);

            return $locked;
        });
        if (! $claimed) {
            $fresh = $message->fresh();

            return is_array($catalog) ? ['message' => $fresh, 'status' => $fresh->status] : $fresh;
        }
        $message = $claimed;
        try {
            $response = $message->message_type === 'catalog'
                ? $this->client->sendCatalog($message->phone, $catalog ?? ($message->payload['catalog'] ?? []))
                : $this->sendText($message->phone, (string) $message->body, (string) $message->provider_idempotency_key);
            $message->update([
                'provider_message_id' => $this->providerIdFromResponse($response),
                'status' => 'sent',
                'attempts' => $message->attempts + 1,
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message->update([
                'status' => 'failed',
                'attempts' => $message->attempts + 1,
                'error' => $exception->getMessage(),
            ]);
        }

        $fresh = $message->fresh();

        return is_array($catalog) ? ['message' => $fresh, 'status' => $fresh->status] : $fresh;
    }

    /** @return array<string, mixed> */
    private function sendText(string $phone, string $body, string $idempotencyKey): array
    {
        if (method_exists($this->client, 'sendTextWithIdempotency')) {
            /** @var array<string, mixed> $response */
            $response = $this->client->sendTextWithIdempotency($phone, $body, $idempotencyKey);

            return $response;
        }

        return $this->client->sendText($phone, $body);
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
