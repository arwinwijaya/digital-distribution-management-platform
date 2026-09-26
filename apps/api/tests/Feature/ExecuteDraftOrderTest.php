<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — T7: `POST /api/admin/recommendation-actions/{id}/execute` for
 * `draft_order`.
 *
 * Execution is approval-gated: only `approved` actions run. The order itself is
 * created by the existing OrderCreationService (never a new mutation path), and
 * the action is marked `executed` with an `execution_result.order_id` in the
 * same orchestration. Replays and races produce exactly one order.
 */
class ExecuteDraftOrderTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return 'Bearer '.app(AuthService::class)->createToken($user)['token'];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function draftOrderAction(string $status = 'approved'): RecommendationAction
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 100, 'price' => 5000]);

        return RecommendationAction::factory()->create([
            'status' => $status,
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'payload' => [
                'type' => 'draft_order',
                'outlet_id' => $outlet->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ],
            'idempotency_key' => 'execute-test-'.uniqid(),
        ]);
    }

    public function test_approved_draft_order_executes_and_creates_exactly_one_order(): void
    {
        $admin = $this->admin();
        $action = $this->draftOrderAction();
        $headers = ['Authorization' => $this->token($admin)];

        $response = $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.executed_by', $admin->id);

        $orderId = $response->json('data.execution_result.order_id');
        $this->assertIsInt($orderId);
        $this->assertGreaterThan(0, $orderId);

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertSame($action->outlet_id, $order->outlet_id);
        $this->assertSame(2, OrderItem::sum('quantity'));
        $this->assertSame(98, Product::findOrFail($action->payload['items'][0]['product_id'])->stock_quantity);

        // Append-only audit: created + approved + executed.
        $events = RecommendationActionEvent::where('recommendation_action_id', $action->id)->get();
        $this->assertSame('executed', $events->firstWhere('event_type', 'executed')?->event_type);
    }

    public function test_execute_replay_does_not_create_duplicate_order(): void
    {
        $admin = $this->admin();
        $action = $this->draftOrderAction();
        $headers = ['Authorization' => $this->token($admin)];

        $first = $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/execute");
        $first->assertOk();
        $firstOrderId = $first->json('data.execution_result.order_id');

        $second = $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/execute");
        $second->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result.order_id', $firstOrderId);

        $this->assertSame(1, Order::count());
        $this->assertSame(
            1,
            RecommendationActionEvent::where('recommendation_action_id', $action->id)->where('event_type', 'executed')->count()
        );
    }

    public function test_unapproved_status_returns_422(): void
    {
        $admin = $this->admin();
        $headers = ['Authorization' => $this->token($admin)];

        foreach (['draft', 'pending_approval', 'rejected', 'failed'] as $status) {
            $action = $this->draftOrderAction($status);
            $this->withHeaders($headers)
                ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
                ->assertStatus(422)
                ->assertJsonPath('status', 'error');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_insufficient_stock_marks_action_failed_without_partial_state(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['stock_quantity' => 1, 'price' => 5000]);
        $outlet = Outlet::factory()->create();

        $action = RecommendationAction::factory()->create([
            'status' => 'approved',
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'payload' => [
                'type' => 'draft_order',
                'outlet_id' => $outlet->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ],
            'idempotency_key' => 'execute-stock-fail-'.uniqid(),
        ]);

        $this->withHeader('Authorization', $this->token($admin))
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        // Action marked failed with a message, no order, stock untouched.
        $fresh = $action->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNotNull($fresh->execution_result);
        $this->assertSame(0, Order::count());
        $this->assertSame(1, Product::findOrFail($product->id)->stock_quantity);
    }

    public function test_non_admin_cannot_execute(): void
    {
        $user = User::factory()->outlet()->create();
        $action = $this->draftOrderAction();

        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
            ->assertForbidden();
    }
}