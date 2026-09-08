<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class WhatsAppPayloadParser
{
    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function extractMessage(array $payload): array
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
    public function extractBody(array $message): ?string
    {
        foreach ([
            $message['text'] ?? null,
            is_array($message['text'] ?? null) ? ($message['text']['body'] ?? null) : null,
            $message['body'] ?? null,
            $message['message'] ?? null,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data @param array<int, string> $keys */
    public function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (is_scalar($data[$key] ?? null) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @return array<int, array{product_id: int, quantity: int}> */
    public function parseItems(string $body, array $payload): array
    {
        if (array_key_exists('items', $payload)) {
            if (! is_array($payload['items']) || ! array_is_list($payload['items']) || count($payload['items']) === 0) {
                throw ValidationException::withMessages(['items' => 'At least one order item is required.']);
            }
            $items = [];
            $productIds = [];
            foreach ($payload['items'] as $index => $item) {
                if (! is_array($item) || ! $this->positiveInteger($item['quantity'] ?? null)) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Each item needs a positive integer quantity and a product.']);
                }
                $product = $this->resolveProduct($item['product_id'] ?? $item['sku'] ?? $item['name'] ?? null);
                if (isset($productIds[$product->id])) {
                    throw ValidationException::withMessages(["items.{$index}" => 'Duplicate product references are not allowed.']);
                }
                $productIds[$product->id] = true;
                $items[] = ['product_id' => $product->id, 'quantity' => (int) $item['quantity']];
            }

            return $items;
        }

        $text = preg_replace('/^\s*(order| pesan)\s*[:\-]?\s*/i', '', trim($body)) ?? '';
        if ($text === '') {
            throw ValidationException::withMessages(['message' => 'Send an order as PRODUCT QUANTITY, for example COFFEE-001 2.']);
        }
        $segments = preg_split('/[,;\n]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $items = [];
        $productIds = [];
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
            if (isset($productIds[$product->id])) {
                throw ValidationException::withMessages(["items.{$index}" => 'Duplicate product references are not allowed.']);
            }
            $productIds[$product->id] = true;
            $items[] = ['product_id' => $product->id, 'quantity' => $quantity];
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one order item is required.']);
        }

        return $items;
    }

    private function positiveInteger(mixed $value): bool
    {
        return (is_int($value) && $value > 0)
            || (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1);
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
}
