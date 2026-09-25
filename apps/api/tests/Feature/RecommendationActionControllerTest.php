<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — T5: `GET/POST /api/admin/recommendation-actions`.
 *
 * HTTP boundary only: the endpoint creates a *draft* action (never executes)
 * and delegates every business decision to RecommendationActionService.
 *
 * Contract: {status,data} envelope, admin-only `rbac:ai_actions:*`,
 * actor from session, idempotent replay, no business-state mutation.
 */
class RecommendationActionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function bearerFor(User $user): string
    {
        return 'Bearer ' . app(AuthService::class)->createToken($user)['token'];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array{outlet: Outlet, product: Product, payload: array<string, mixed>}
     */
    private function draftOrderFixture(): array
    {
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        return [
            'outlet' => $outlet,
            'product' => $product,
            'payload' => [
                'type' => 'draft_order',
                'outlet_id' => $outlet->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
                'idempotency_key' => 'phase9-draft-order-1',
            ],
        ];
    }

    // ------------------------------------------------------------ happy path

    public function test_admin_can_create_draft_order_action(): void
    {
        $admin = $this->admin();
        $fixture = $this->draftOrderFixture();

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $fixture['payload']);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.type', 'draft_order')
            ->assertJsonPath('data.outlet_id', $fixture['outlet']->id)
            ->assertJsonPath('data.created_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);

        // Draft-only: no business state is mutated.
        $this->assertSame(0, Order::count());
        $this->assertSame(1, RecommendationAction::count());
        $this->assertSame('draft', RecommendationAction::first()->status);

        // One append-only audit event, attributed to the session actor.
        $this->assertSame(1, RecommendationActionEvent::count());
        $event = RecommendationActionEvent::first();
        $this->assertSame('created', $event->event_type);
        $this->assertSame($admin->id, $event->actor_id);
    }

    public function test_admin_can_create_draft_campaign_action_without_creating_promotion(): void
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', [
                'type' => 'draft_campaign',
                'outlet_id' => $outlet->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'idempotency_key' => 'phase9-draft-campaign-1',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.type', 'draft_campaign');

        $this->assertSame(0, Promotion::count());
        $this->assertSame(1, RecommendationAction::count());
    }

    public function test_actor_is_taken_from_session_not_payload(): void
    {
        $admin = $this->admin();
        $spoofed = User::factory()->admin()->create();
        $fixture = $this->draftOrderFixture();

        $payload = array_merge($fixture['payload'], ['created_by' => $spoofed->id]);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $payload)
            ->assertCreated()
            ->assertJsonPath('data.created_by', $admin->id);

        $this->assertSame($admin->id, RecommendationAction::first()->created_by);
        $this->assertNotSame($spoofed->id, RecommendationAction::first()->created_by);
    }

    // ---------------------------------------------------------- idempotency

    public function test_identical_replay_returns_existing_action_with_flag(): void
    {
        $admin = $this->admin();
        $fixture = $this->draftOrderFixture();

        $first = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $fixture['payload']);
        $first->assertCreated();
        $firstId = $first->json('data.id');

        $second = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $fixture['payload']);

        $second->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, RecommendationAction::count());
        $this->assertSame(1, RecommendationActionEvent::count());
    }

    public function test_conflicting_payload_with_same_key_returns_409(): void
    {
        $admin = $this->admin();
        $fixture = $this->draftOrderFixture();
        $otherProduct = Product::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $fixture['payload'])
            ->assertCreated();

        $conflict = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', array_merge($fixture['payload'], [
                'items' => [['product_id' => $otherProduct->id, 'quantity' => 5]],
            ]));

        $conflict->assertStatus(409)->assertJsonPath('status', 'error');

        $this->assertSame(1, RecommendationAction::count());
        $this->assertSame(1, RecommendationActionEvent::count());
    }

    // ----------------------------------------------------------- validation

    public function test_missing_items_returns_422(): void
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', [
                'type' => 'draft_order',
                'outlet_id' => $outlet->id,
                'idempotency_key' => 'phase9-missing-items',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['items']);

        $this->assertSame(0, RecommendationAction::count());
    }

    public function test_invalid_type_returns_422(): void
    {
        $admin = $this->admin();
        $fixture = $this->draftOrderFixture();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', array_merge($fixture['payload'], [
                'type' => 'execute_order',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        $this->assertSame(0, RecommendationAction::count());
    }

    public function test_missing_idempotency_key_returns_422(): void
    {
        $admin = $this->admin();
        $fixture = $this->draftOrderFixture();
        $payload = $fixture['payload'];
        unset($payload['idempotency_key']);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/recommendation-actions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    // ------------------------------------------------------------ authz/RBAC

    public function test_non_admin_cannot_create_action(): void
    {
        $fixture = $this->draftOrderFixture();

        $this->withHeader('Authorization', $this->bearerFor(User::factory()->outlet()->create()))
            ->postJson('/api/admin/recommendation-actions', $fixture['payload'])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $this->assertSame(0, RecommendationAction::count());
    }

    public function test_non_admin_cannot_list_actions(): void
    {
        $this->withHeader('Authorization', $this->bearerFor(User::factory()->finance()->create()))
            ->getJson('/api/admin/recommendation-actions')
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    public function test_unauthenticated_is_401(): void
    {
        $this->getJson('/api/admin/recommendation-actions')->assertUnauthorized();
    }

    // ---------------------------------------------------------------- list

    public function test_admin_can_list_actions_with_pagination_meta(): void
    {
        $admin = $this->admin();

        // id 1 = oldest ... id 3 = newest (created_at DESC order is 3,2,1).
        for ($i = 1; $i <= 3; $i++) {
            RecommendationAction::factory()->create([
                'status' => 'draft',
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $page1 = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson('/api/admin/recommendation-actions?limit=2');

        $page1->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.limit', 2)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.has_more', true);

        $page2 = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson('/api/admin/recommendation-actions?limit=2&cursor=2');

        $page2->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_admin_can_filter_actions_by_status(): void
    {
        $admin = $this->admin();

        RecommendationAction::factory()->create(['status' => 'draft']);
        RecommendationAction::factory()->create(['status' => 'approved']);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson('/api/admin/recommendation-actions?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'draft');
    }
}
