<?php

namespace App\Services;

use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OrderCreationService;
use App\Services\PromotionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecommendationActionService
{
    public const VALID_TYPES = ['draft_order', 'draft_campaign'];

    public function __construct(
        private readonly ?OrderCreationService $orderCreationService = null,
        private readonly ?PromotionService $promotionService = null,
    ) {}

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
     * Approve a draft action (draft -> approved).
     *
     * The status transition and the append-only audit row are written inside a
     * single row-locked transaction, mirroring OrderController::runApprovalTransaction.
     * A replay against an already-approved action is idempotent: it returns the
     * same row without appending a second audit event. Any other status is an
     * illegal transition (422).
     *
     * Actor is always taken from the $actor argument, never from payload.
     *
     * @return array{action: RecommendationAction, replay: bool}
     */
    public function approve(int $id, User $actor): array
    {
        return $this->runTransition($id, function (RecommendationAction $action) use ($actor): array {
            if ($action->status === 'approved') {
                return ['action' => $action->load('events'), 'replay' => true];
            }

            if ($action->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => "Cannot approve action with status '{$action->status}'.",
                ]);
            }

            $action->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->appendEvent($action, 'approved', $actor->id);

            return ['action' => $action->load('events'), 'replay' => false];
        });
    }

    /**
     * Reject a draft action (draft -> rejected).
     *
     * A rejection reason is mandatory. As with approve(), the transition and its
     * audit row are committed in one row-locked transaction, and a replay against
     * an already-rejected action does not duplicate the audit event.
     *
     * @return array{action: RecommendationAction, replay: bool}
     */
    public function reject(int $id, User $actor, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        return $this->runTransition($id, function (RecommendationAction $action) use ($actor, $reason): array {
            if ($action->status === 'rejected') {
                return ['action' => $action->load('events'), 'replay' => true];
            }

            if ($action->status !== 'draft') {
                throw ValidationException::withMessages([
                    'status' => "Cannot reject action with status '{$action->status}'.",
                ]);
            }

            $action->forceFill([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ])->save();

            $this->appendEvent($action, 'rejected', $actor->id, ['reason' => $reason]);

            return ['action' => $action->load('events'), 'replay' => false];
        });
    }

    /**
     * Run a status transition under a row lock with bounded retry on transient
     * database errors, matching OrderController::runApprovalTransaction.
     *
     * @param  callable(RecommendationAction): array{action: RecommendationAction, replay: bool}  $mutator
     * @return array{action: RecommendationAction, replay: bool}
     */
    private function runTransition(int $id, callable $mutator): array
    {
        $result = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $result = DB::transaction(function () use ($id, $mutator): array {
                    $action = RecommendationAction::whereKey($id)->lockForUpdate()->first();
                    if ($action === null) {
                        throw (new ModelNotFoundException())->setModel(RecommendationAction::class, [$id]);
                    }

                    return $mutator($action);
                });
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

    /**
     * Append-only audit row for a transition. No update/delete path exists.
     */
    private function appendEvent(RecommendationAction $action, string $eventType, ?int $actorId, array $extra = []): void
    {
        RecommendationActionEvent::create([
            'recommendation_action_id' => $action->id,
            'event_type' => $eventType,
            'actor_id' => $actorId,
            'metadata' => array_merge(['at' => now()->toISOString()], $extra),
        ]);
    }

    /**
     * Run a locked orchestration block with bounded retry on transient database
     * errors, matching the approval-transition retry posture.
     *
     * @param  callable(): array{action: RecommendationAction, replay: bool}  $body
     * @return array{action: RecommendationAction, replay: bool}
     */
    private function runExecution(int $id, callable $body): array
    {
        $result = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $result = DB::transaction(function () use ($id, $body): array {
                    $action = RecommendationAction::whereKey($id)->lockForUpdate()->first();
                    if ($action === null) {
                        throw (new ModelNotFoundException())->setModel(RecommendationAction::class, [$id]);
                    }

                    return $body($action);
                });
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

    /**
     * Execute an approved action. Only actions with status 'approved' may execute.
     *
     * The execution identity derives from the action id so that replaying the
     * same endpoint produces exactly one business effect (Order/Promotion).
     *
     * @return array{action: RecommendationAction, replay: bool, failed: bool}
     */
    public function execute(int $id, User $actor): array
    {
        return $this->runExecution($id, function (RecommendationAction $action) use ($actor): array {
            // Idempotent replay: already executed → return existing outcome.
            if ($action->status === 'executed') {
                return ['action' => $action->load('events'), 'replay' => true, 'failed' => false];
            }

            // Gate: only approved actions execute.
            if ($action->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => "Cannot execute action with status '{$action->status}'. Only approved actions may be executed.",
                ]);
            }

            $actionType = $action->type;
            $payload = $action->payload;
            $identity = 'recommendation-action:'.$action->id;

            try {
                if ($actionType === 'draft_order') {
                    return $this->executeDraftOrder($action, $payload, $identity, $actor);
                }

                if ($actionType === 'draft_campaign') {
                    return $this->executeDraftCampaign($action, $payload, $identity, $actor);
                }

                throw ValidationException::withMessages([
                    'status' => "Unknown action type '{$actionType}'.",
                ]);
            } catch (ValidationException $e) {
                // Business failure (stock, overlap, etc.) → mark failed, persist
                // execution_result with the message, append 'failed' event, commit.
                // The inner service has already rolled back its savepoint.
                $action->forceFill([
                    'status' => 'failed',
                    'executed_by' => $actor->id,
                    'executed_at' => now(),
                    'execution_result' => ['error' => $e->getMessage()],
                ])->save();

                $this->appendEvent($action, 'failed', $actor->id, [
                    'error' => $e->getMessage(),
                ]);

                // Return failed outcome (HTTP 200 with data.status='failed').
                return ['action' => $action->load('events'), 'replay' => false, 'failed' => true];
            }
        });
    }

    /**
     * Execute a draft_order by delegating to OrderCreationService.
     *
     * @param  array{items: array<int, array{product_id: int, quantity: int}>}  $payload
     */
    private function executeDraftOrder(RecommendationAction $action, array $payload, string $identity, User $actor): array
    {
        $outlet = Outlet::findOrFail($action->outlet_id);
        $validated = ['items' => $payload['items']];
        $orders = $this->orderCreationService ?? app(OrderCreationService::class);

        // OrderCreationService runs in a nested transaction (savepoint).
        // On success, the order is committed. On ValidationException, the
        // savepoint rolls back and we catch above to mark the action failed.
        $result = $orders->create($validated, $outlet, $identity);

        $order = $result['order'];

        $action->forceFill([
            'status' => 'executed',
            'executed_by' => $actor->id,
            'executed_at' => now(),
            'execution_result' => [
                'order_id' => $order->id,
                'created' => $result['created'],
            ],
        ])->save();

        $this->appendEvent($action, 'executed', $actor->id, [
            'order_id' => $order->id,
        ]);

        return ['action' => $action->load('events'), 'replay' => false, 'failed' => false];
    }

    /**
     * Execute a draft_campaign by delegating to PromotionService::create() for
     * overlap checking and actor audit (created_by is the session actor).
     *
     * @param  array{campaign: array<string, mixed>}  $payload
     */
    private function executeDraftCampaign(RecommendationAction $action, array $payload, string $identity, User $actor): array
    {
        if (! isset($payload['campaign']) || ! is_array($payload['campaign'])) {
            throw ValidationException::withMessages([
                'payload' => 'Campaign payload is missing required fields.',
            ]);
        }

        $campaign = $payload['campaign'];
        $promotions = $this->promotionService ?? app(PromotionService::class);

        // PromotionService::create() performs the overlap check + persists
        // with created_by = actor (session). It runs in its own nested
        // transaction (savepoint); on overlap/validation failure the
        // savepoint rolls back and we catch above to mark failed.
        $promotion = $promotions->create($campaign, $actor->id);

        $action->forceFill([
            'status' => 'executed',
            'executed_by' => $actor->id,
            'executed_at' => now(),
            'execution_result' => [
                'promotion_id' => $promotion->id,
            ],
        ])->save();

        $this->appendEvent($action, 'executed', $actor->id, [
            'promotion_id' => $promotion->id,
        ]);

        return ['action' => $action->load('events'), 'replay' => false, 'failed' => false];
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