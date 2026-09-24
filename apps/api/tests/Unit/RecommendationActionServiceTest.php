<?php

namespace Tests\Unit;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Services\RecommendationActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecommendationActionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecommendationActionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationActionService();
    }

    public function test_createDraft_draftOrder_createsDraftActionWithCreatedAudit(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
        $idempotencyKey = 'test-key-draft-order-1';

        $result = $this->service->createDraft($payload, $actor, $idempotencyKey);

        $this->assertTrue($result['created']);
        $this->assertInstanceOf(RecommendationAction::class, $result['action']);
        $action = $result['action'];

        $this->assertSame('draft_order', $action->type);
        $this->assertSame('draft', $action->status);
        $this->assertSame($idempotencyKey, $action->idempotency_key);
        $this->assertSame($actor->id, $action->created_by);
        $this->assertSame($outlet->id, $action->outlet_id);
        $this->assertNotNull($action->idempotency_payload_hash);
        $this->assertSame($payload['items'], $action->payload['items']);
        $this->assertSame($outlet->id, $action->payload['outlet_id']);

        // Audit event 'created'
        $events = $action->events;
        $this->assertCount(1, $events);
        $this->assertSame('created', $events[0]->event_type);
        $this->assertSame($actor->id, $events[0]->actor_id);

        // No Order created (draft only)
        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_createDraft_draftCampaign_createsDraftActionWithCreatedAudit(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $payload = [
            'type' => 'draft_campaign',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
        $idempotencyKey = 'test-key-draft-campaign-1';

        $result = $this->service->createDraft($payload, $actor, $idempotencyKey);

        $this->assertTrue($result['created']);
        $action = $result['action'];

        $this->assertSame('draft_campaign', $action->type);
        $this->assertSame('draft', $action->status);
        $this->assertSame($idempotencyKey, $action->idempotency_key);
        $this->assertSame($actor->id, $action->created_by);
        $this->assertSame($outlet->id, $action->outlet_id);

        // Audit event 'created'
        $events = $action->events;
        $this->assertCount(1, $events);
        $this->assertSame('created', $events[0]->event_type);

        // No Promotion created (draft only)
        $this->assertSame(0, \App\Models\Promotion::count());
    }

    public function test_createDraft_idempotentReplay_samePayload_returnsExistingAction(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
        $idempotencyKey = 'test-key-idempotent-1';

        // First call
        $first = $this->service->createDraft($payload, $actor, $idempotencyKey);
        $this->assertTrue($first['created']);

        // Second call with same idempotency_key and payload
        $second = $this->service->createDraft($payload, $actor, $idempotencyKey);
        $this->assertFalse($second['created']);
        $this->assertSame($first['action']->id, $second['action']->id);

        // Only one action row exists
        $this->assertSame(1, RecommendationAction::count());
        // Only one 'created' audit event
        $this->assertSame(1, RecommendationActionEvent::count());
    }

    public function test_createDraft_idempotentConflict_differentPayload_throws422(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();
        $product2 = Product::factory()->create();

        $payload1 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
        $idempotencyKey = 'test-key-conflict-1';

        $this->service->createDraft($payload1, $actor, $idempotencyKey);

        $payload2 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product2->id, 'quantity' => 3],
            ],
        ];

        try {
            $this->service->createDraft($payload2, $actor, $idempotencyKey);
            $this->fail('Expected ValidationException for idempotency conflict.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
    }

    public function test_createDraft_unknownType_throws422(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();

        $payload = [
            'type' => 'unknown_type',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ];
        $idempotencyKey = 'test-key-invalid-type';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('type');

        $this->service->createDraft($payload, $actor, $idempotencyKey);
    }

    public function test_createDraft_emptyItems_throws422(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [],
        ];
        $idempotencyKey = 'test-key-empty-items';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('items');

        $this->service->createDraft($payload, $actor, $idempotencyKey);
    }

    public function test_createDraft_missingItems_throws422(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
        ];
        $idempotencyKey = 'test-key-missing-items';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('items');

        $this->service->createDraft($payload, $actor, $idempotencyKey);
    }

    public function test_createDraft_missingOutletId_throws422(): void
    {
        $actor = User::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ];
        $idempotencyKey = 'test-key-missing-outlet';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('outlet_id');

        $this->service->createDraft($payload, $actor, $idempotencyKey);
    }

    public function test_createDraft_actorFromArgument_notFromPayload(): void
    {
        $actor = User::factory()->create();
        $otherUser = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $payload = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'created_by' => $otherUser->id, // Should be ignored
        ];
        $idempotencyKey = 'test-key-actor-arg';

        $result = $this->service->createDraft($payload, $actor, $idempotencyKey);

        $this->assertSame($actor->id, $result['action']->created_by);
        $this->assertNotSame($otherUser->id, $result['action']->created_by);
    }

    public function test_createDraft_payloadFingerprint_canonicalSorting(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // Same items, different order
        $payload1 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product2->id, 'quantity' => 3],
                ['product_id' => $product1->id, 'quantity' => 1],
            ],
        ];
        $idempotencyKey = 'test-key-canonical-1';

        $result1 = $this->service->createDraft($payload1, $actor, $idempotencyKey);
        $hash1 = $result1['action']->idempotency_payload_hash;

        // Replay with items in different order - should be idempotent
        $payload2 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 1],
                ['product_id' => $product2->id, 'quantity' => 3],
            ],
        ];

        $result2 = $this->service->createDraft($payload2, $actor, $idempotencyKey);
        $hash2 = $result2['action']->idempotency_payload_hash;

        $this->assertSame($hash1, $hash2);
        $this->assertFalse($result2['created']);
        $this->assertSame($result1['action']->id, $result2['action']->id);
    }

    public function test_createDraft_quantityChangeChangesFingerprint(): void
    {
        $actor = User::factory()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $payload1 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ];
        $idempotencyKey = 'test-key-qty-change';

        $this->service->createDraft($payload1, $actor, $idempotencyKey);

        $payload2 = [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3], // Different quantity
            ],
        ];

        try {
            $this->service->createDraft($payload2, $actor, $idempotencyKey);
            $this->fail('Expected ValidationException for quantity change conflict.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
    }
}