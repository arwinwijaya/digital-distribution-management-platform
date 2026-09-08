<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\WhatsAppMessage;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppService
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly WhatsAppClient $client,
    ) {}

    /** @param array<string, mixed> $payload @param array<string, string|null> $headers */
    public function handleWebhook(array $payload, string $rawBody, array $headers): array
    {
        if (! config('whatsapp.enabled')) {
            return ['http_status' => 202, 'status' => 'disabled', 'message' => 'WhatsApp integration is currently unavailable.'];
        }
        if (! $this->validSignature($rawBody, $headers)) {
            return ['http_status' => 401, 'status' => 'error', 'message' => 'Invalid or missing webhook signature.'];
        }

        $message = $this->extractMessage($payload);
        $providerId = $this->firstString($message, ['provider_message_id', 'message_id', 'event_id', 'id']);
        $phone = $this->firstString($message, ['from', 'phone', 'phone_number', 'sender']);
        $body = $this->extractBody($message);

        if (! $providerId || ! $phone || ! $body) {
            return ['http_status' => 422, 'status' => 'error', 'message' => 'Webhook must include a message id, sender phone, and text message.'];
        }

        $inbound = $this->persistInbound($providerId, $phone, $body, $payload);
        if (! $inbound['created']) {
            return [
                'http_status' => 200,
                'status' => 'duplicate',
                'duplicate' => true,
                'message' => 'Event was already processed.',
                'data' => ['order_id' => $inbound['message']->order_id],
            ];
        }
        /** @var WhatsAppMessage $record */
        $record = $inbound['message'];

        $outlet = $this->findOutletByPhone($phone);
        if (! $outlet) {
            $record->update(['status' => 'rejected', 'error' => 'Sender is not mapped to an active outlet.']);

            return ['http_status' => 422, 'status' => 'error', 'message' => 'Sender is not mapped to an active outlet.'];
        }

        try {
            $items = $this->parseItems($body, $payload);
            $result = $this->orderCreationService->create(
                ['items' => $items],
                $outlet,
                hash('sha256', 'whatsapp:'.$providerId),
            );
            $order = $result['order'];
            $record->update(['status' => 'processed', 'outlet_id' => $outlet->id, 'order_id' => $order->id]);
            $order->load('items.product');

            return [
                'http_status' => $result['created'] ? 201 : 200,
                'status' => $result['created'] ? 'success' : 'duplicate',
                'duplicate' => ! $result['created'],
                'data' => $order,
            ];
        } catch (ValidationException $exception) {
            $record->update(['status' => 'rejected', 'outlet_id' => $outlet->id, 'error' => $this->validationMessage($exception)]);

            return [
                'http_status' => 422,
                'status' => 'error',
                'message' => $this->validationMessage($exception),
                'errors' => $exception->errors(),
            ];
        } catch (Throwable $exception) {
            $record->update(['status' => 'failed', 'outlet_id' => $outlet->id, 'error' => $exception->getMessage()]);

            return ['http_status' => 503, 'status' => 'error', 'message' => 'The order could not be accepted. Please retry the message.'];
        }
    }

    public function notifyConfirmedOrder(Order $order): ?WhatsAppMessage
    {
        if (! $order->outlet || ! config('whatsapp.enabled')) {
            return null;
        }

        $message = WhatsAppMessage::create([
            'direction' => 'outbound',
            'phone' => $order->outlet->phone,
            'message_type' => 'order_confirmation',
            'body' => "Order {$order->order_id} is confirmed.",
            'status' => 'pending',
            'outlet_id' => $order->outlet_id,
            'order_id' => $order->id,
        ]);

        try {
            $response = $this->client->sendText($order->outlet->phone, $message->body);
            $message->update([
                'provider_message_id' => $this->providerIdFromResponse($response),
                'status' => 'sent',
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message->update(['status' => 'failed', 'attempts' => 1, 'error' => $exception->getMessage()]);
        }

        return $message->fresh();
    }

    public function retryMessage(WhatsAppMessage $message): WhatsAppMessage
    {
        if (! config('whatsapp.enabled')) {
            return $message;
        }
        if ($message->direction !== 'outbound' || $message->status === 'sent') {
            return $message;
        }

        try {
            $response = $message->message_type === 'catalog'
                ? $this->client->sendCatalog($message->phone, $message->payload['catalog'] ?? [])
                : $this->client->sendText($message->phone, (string) $message->body);
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

        return $message->fresh();
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

        try {
            $response = $this->client->sendCatalog($outlet->phone, $products);
            $message->update([
                'provider_message_id' => $this->providerIdFromResponse($response),
                'status' => 'sent',
                'attempts' => 1,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message->update(['status' => 'failed', 'attempts' => 1, 'error' => $exception->getMessage()]);
        }

        return ['message' => $message->fresh(), 'status' => $message->status];
    }

    private function validSignature(string $rawBody, array $headers): bool
    {
        if (! config('whatsapp.verify_signature', true)) {
            return true;
        }
        $secret = config('whatsapp.webhook_secret');
        $provided = $headers['x-hub-signature-256'] ?? $headers['x-whatsapp-signature'] ?? null;
        if ($secret && $provided) {
            $provided = preg_replace('/^sha256=/i', '', trim($provided));
            if (is_string($provided) && hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided)) {
                return true;
            }
        }

        // Some approved providers expose a shared verification token instead of
        // an HMAC header. It is still compared in constant time and never used
        // to identify an outlet.
        $token = config('whatsapp.verify_token');
        $providedToken = $headers['x-whatsapp-token'] ?? null;

        return $token && $providedToken && hash_equals((string) $token, (string) $providedToken);
    }

    /** @return array{message: WhatsAppMessage, created: bool} */
    private function persistInbound(string $providerId, string $phone, string $body, array $payload): array
    {
        $existing = WhatsAppMessage::where('provider_message_id', $providerId)->first();
        if ($existing) {
            return ['message' => $existing, 'created' => false];
        }

        try {
            return [
                'message' => WhatsAppMessage::create([
                    'provider_message_id' => $providerId,
                    'direction' => 'inbound',
                    'phone' => $phone,
                    'message_type' => 'text',
                    'body' => $body,
                    'payload' => $payload,
                    'status' => 'processing',
                ]),
                'created' => true,
            ];
        } catch (QueryException) {
            $existing = WhatsAppMessage::where('provider_message_id', $providerId)->firstOrFail();

            return ['message' => $existing, 'created' => false];
        }
    }

    private function findOutletByPhone(string $phone): ?Outlet
    {
        $normalized = $this->normalizePhone($phone);

        return Outlet::query()->where('is_active', true)->get()->first(
            fn (Outlet $outlet) => $this->normalizePhone($outlet->phone) === $normalized
        );
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return $digits;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function extractMessage(array $payload): array
    {
        if (isset($payload['messages'][0]) && is_array($payload['messages'][0])) {
            return array_merge($payload, $payload['messages'][0]);
        }
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (is_array($value) && isset($value['messages'][0]) && is_array($value['messages'][0])) {
            return array_merge($payload, $value, $value['messages'][0]);
        }

        return $payload;
    }

    /** @param array<string, mixed> $message */
    private function extractBody(array $message): ?string
    {
        if (is_string($message['text'] ?? null)) {
            return trim($message['text']);
        }
        if (is_array($message['text'] ?? null) && is_string($message['text']['body'] ?? null)) {
            return trim($message['text']['body']);
        }
        if (is_string($message['body'] ?? null)) {
            return trim($message['body']);
        }
        if (is_string($message['message'] ?? null)) {
            return trim($message['message']);
        }

        return null;
    }

    /** @param array<string, mixed> $data @param array<int, string> $keys */
    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_scalar($data[$key] ?? null) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @return array<int, array{product_id: int, quantity: int}> */
    private function parseItems(string $body, array $payload): array
    {
        if (isset($payload['items']) && is_array($payload['items'])) {
            return collect($payload['items'])->map(function ($item, $index) {
                if (! is_array($item) || ! is_numeric($item['quantity'] ?? null) || (int) $item['quantity'] < 1) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Each item needs a positive integer quantity and a product.']);
                }
                $product = $this->resolveProduct($item['product_id'] ?? $item['sku'] ?? $item['name'] ?? null);

                return ['product_id' => $product->id, 'quantity' => (int) $item['quantity']];
            })->values()->all();
        }

        $text = preg_replace('/^\s*(order| pesan)\s*[:\-]?\s*/i', '', trim($body)) ?? '';
        if ($text === '') {
            throw ValidationException::withMessages(['message' => 'Send an order as PRODUCT QUANTITY, for example COFFEE-001 2.']);
        }
        $segments = preg_split('/[,;\n]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $items = [];
        foreach ($segments as $index => $segment) {
            $segment = trim($segment);
            $productText = null;
            $quantity = null;
            if (preg_match('/^(\d+)\s*[xX]?\s+(.+)$/', $segment, $match)) {
                $quantity = (int) $match[1];
                $productText = trim($match[2]);
            } elseif (preg_match('/^(.+?)\s+[xX]\s*(\d+)$/i', $segment, $match)) {
                $productText = trim($match[1]);
                $quantity = (int) $match[2];
            } elseif (preg_match('/^(.+?)\s+(\d+)$/', $segment, $match)) {
                $productText = trim($match[1]);
                $quantity = (int) $match[2];
            }
            if (! $productText || ! $quantity || $quantity < 1) {
                throw ValidationException::withMessages(["items.{$index}" => 'Use an unambiguous PRODUCT QUANTITY format, for example COFFEE-001 2.']);
            }
            try {
                $product = $this->resolveProduct($productText);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(["items.{$index}" => $this->validationMessage($exception)]);
            }
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity];
        }

        return $items;
    }

    private function resolveProduct(mixed $reference): Product
    {
        if (! is_scalar($reference) || trim((string) $reference) === '') {
            throw ValidationException::withMessages(['message' => 'A product SKU or exact product name is required.']);
        }
        $value = trim((string) $reference);
        $query = Product::query()->where('is_active', true);
        if (ctype_digit($value)) {
            $query->whereKey((int) $value);
        } else {
            $query->where(function ($q) use ($value) {
                $q->whereRaw('LOWER(sku) = ?', [strtolower($value)])
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($value)]);
            });
        }
        $products = $query->get();
        if ($products->count() === 0) {
            throw ValidationException::withMessages(['message' => "Product '{$value}' was not found or is unavailable."]);
        }
        if ($products->count() > 1) {
            throw ValidationException::withMessages(['message' => "Product '{$value}' is ambiguous; use its SKU."]);
        }

        return $products->first();
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
    }

    /** @param array<string, mixed> $response */
    private function providerIdFromResponse(array $response): ?string
    {
        return $this->firstString($response, ['provider_message_id', 'message_id', 'id'])
            ?? data_get($response, 'messages.0.id');
    }
}
