<?php

namespace App\Services;

use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecommendationActionService
{
    public const VALID_TYPES = ['draft_order', 'draft_campaign'];

    /**
     * Create a draft recommendation action with idempotency.
     *
     * Draft actions are read-only placeholders; no business state (Order/Promotion) is mutated.
     * Actor is always taken from the $actor argument, never from payload.
     *
     * @param  array{
     *     type: 'draft_order'|'draft_campaign',
     *     outlet_id: int,
     *     items: array<int, array{product_id: int, quantity: int}>
     * }  $payload
     * @return array{action: RecommendationAction, created: bool}
     */
    public function createDraft(array $payload, User $actor, string $idempotencyKey): array
    {
        $this->validatePayload($payload);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($payload, $actor, $idempotencyKey) {
                    $existing = RecommendationAction::where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        $this->assertSamePayload($existing, $payload);
                        return ['action' => $existing, 'created' => false];
                    }

                    $fingerprint = self::payloadFingerprint($payload['items']);
                    $action = RecommendationAction::create([
                        'source_event_id' => $payload['source_event_id'] ?? null,
                        'outlet_id' => $payload['outlet_id'],
                        'created_by' => $actor->id,
                        'type' => $payload['type'],
                        'status' => 'draft',
                        'payload' => $this->canonicalizePayload($payload),
                        'idempotency_key' => $idempotencyKey,
                        'idempotency_payload_hash' => $fingerprint,
                        'method' => 'deterministic',
                        'method_version' => 'v1',
                        'fallback' => false,
                        'data_sufficiency' => 'sufficient',
                        'metadata' => null,
                    ]);

                    RecommendationActionEvent::create([
                        'recommendation_action_id' => $action->id,
                        'event_type' => 'created',
                        'actor_id' => $actor->id,
                        'metadata' => ['at' => now()->toISOString()],
                    ]);

                    return ['action' => $action->load('events'), 'created' => true];
                });
            } catch (QueryException $exception) {
                $existing = RecommendationAction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    $this->assertSamePayload($existing, $payload);
                    return ['action' => $existing->load('events'), 'created' => false];
                }

                if ($attempt === 2) {
                    throw $exception;
                }

                usleep(10000 * ($attempt + 1));
            }
        }

        throw new \LogicException('Unable to create draft action.');
    }

    /**
     * Validate payload structure.
     *
     * @param  array{
     *     type?: string,
     *     outlet_id?: int,
     *     items?: array<int, array{product_id: int, quantity: int}>
     * }  $payload
     */
    private function validatePayload(array $payload): void
    {
        $errors = [];

        $type = $payload['type'] ?? null;
        if (! $type || ! in_array($type, self::VALID_TYPES, true)) {
            $errors['type'] = 'The type must be one of: ' . implode(', ', self::VALID_TYPES) . '.';
        }

        $outletId = $payload['outlet_id'] ?? null;
        if (! isset($payload['outlet_id']) || ! is_int($outletId) || $outletId <= 0) {
            $errors['outlet_id'] = 'The outlet_id is required and must be a positive integer.';
        }

        $items = $payload['items'] ?? null;
        if (! isset($payload['items']) || ! is_array($items) || count($items) === 0) {
            $errors['items'] = 'The items array is required and must contain at least one item.';
        } else {
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    $errors["items.{$index}"] = 'Each item must be an array.';
                    continue;
                }
                $productId = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? null;
                if (! isset($item['product_id']) || ! is_int($productId) || $productId <= 0) {
                    $errors["items.{$index}.product_id"] = 'Each item must have a positive integer product_id.';
                }
                if (! isset($item['quantity']) || ! is_int($quantity) || $quantity <= 0) {
                    $errors["items.{$index}.quantity"] = 'Each item must have a positive integer quantity.';
                }
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Canonical payload for storage (sorted keys, deterministic).
     *
     * @param  array{
     *     type: 'draft_order'|'draft_campaign',
     *     outlet_id: int,
     *     items: array<int, array{product_id: int, quantity: int}>
     * }  $payload
     */
    private function canonicalizePayload(array $payload): array
    {
        return [
            'type' => $payload['type'],
            'outlet_id' => (int) $payload['outlet_id'],
            'items' => collect($payload['items'])
                ->map(fn ($item) => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ])
                ->sortBy('product_id')
                ->values()
                ->all(),
        ];
    }

    /**
     * Canonical SHA-256 fingerprint of the line items.
     * Matches OrderCreationService::payloadFingerprint pattern.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public static function payloadFingerprint(array $items): string
    {
        $canonical = collect($items)
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
     * Throw 422 when replayed payload differs from stored fingerprint.
     *
     * @param  array{
     *     type: 'draft_order'|'draft_campaign',
     *     outlet_id: int,
     *     items: array<int, array{product_id: int, quantity: int}>
     * }  $payload
     */
    private function assertSamePayload(RecommendationAction $existing, array $payload): void
    {
        $fingerprint = self::payloadFingerprint($payload['items']);

        if ($existing->idempotency_payload_hash !== null) {
            if (! hash_equals($existing->idempotency_payload_hash, $fingerprint)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This action request identity was already used with a different payload.',
                ]);
            }
            return;
        }

        // Legacy row without fingerprint: compare canonicalized stored payload
        $existingPayload = $existing->payload;
        if (! is_array($existingPayload)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This action request identity was already used with a different payload.',
            ]);
        }

        $canonical = $this->canonicalizePayload($payload);
        if ($existingPayload !== $canonical) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This action request identity was already used with a different payload.',
            ]);
        }
    }
}