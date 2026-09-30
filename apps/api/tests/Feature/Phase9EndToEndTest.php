<?php

namespace Tests\Feature;

use App\Models\ForecastCalibration;
use App\Models\Order;
use App\Models\OperationalEvent;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RecommendationActionEvent;
use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\RevenueLiftSnapshot;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 T18 — cross-unit integration verification.
 *
 * Real HTTP boundary for available Phase 9 mutation routes. Replenishment plan
 * generation/list and calibration/lift HTTP endpoints do not exist in the
 * backend route table, so those contracts are exercised via existing factories
 * and services in cycle 2 without inventing new endpoints.
 */
class Phase9EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function bearerFor(User $user): string
    {
        return 'Bearer '.app(AuthService::class)->createToken($user)['token'];
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function nonAdmin(): User
    {
        return User::factory()->outlet()->create();
    }

    public function test_recommendation_action_order_campaign_lifecycle_is_approval_gated_idempotent_audited_and_admin_only(): void
    {
        $admin = $this->admin();
        $nonAdmin = $this->nonAdmin();
        $headers = ['Authorization' => $this->bearerFor($admin)];
        $nonAdminHeaders = ['Authorization' => $this->bearerFor($nonAdmin)];

        $outlet = Outlet::factory()->create();
        $orderProduct = Product::factory()->create(['stock_quantity' => 100, 'price' => 5000]);
        $campaignProduct = Product::factory()->create(['stock_quantity' => 100, 'price' => 7500]);

        $orderCreate = $this->withHeaders($headers)->postJson('/api/admin/recommendation-actions', [
            'type' => 'draft_order',
            'outlet_id' => $outlet->id,
            'items' => [['product_id' => $orderProduct->id, 'quantity' => 3]],
            'idempotency_key' => 'phase9-e2e-order',
        ]);
        $orderCreate->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.type', 'draft_order')
            ->assertJsonPath('data.created_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);
        $orderActionId = (int) $orderCreate->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/execute")
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertSame(0, Order::count());

        $this->withHeaders($nonAdminHeaders)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/approve")
            ->assertForbidden();

        $orderApprove = $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/approve", ['approved_by' => $nonAdmin->id]);
        $orderApprove->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/approve")
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $orderExecute = $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/execute");
        $orderExecute->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.executed_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);
        $orderId = $orderExecute->json('data.execution_result.order_id');
        $this->assertIsInt($orderId);
        $this->assertSame(1, Order::count());

        $this->withHeaders($nonAdminHeaders)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/execute")
            ->assertForbidden();

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$orderActionId}/execute")
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result.order_id', $orderId);
        $this->assertSame(1, Order::count());

        $campaignCreate = $this->withHeaders($headers)->postJson('/api/admin/recommendation-actions', [
            'type' => 'draft_campaign',
            'outlet_id' => $outlet->id,
            'items' => [['product_id' => $campaignProduct->id, 'quantity' => 1]],
            'campaign' => [
                'name' => 'Phase 9 End-to-End Campaign',
                'description' => 'Created through recommendation action execution.',
                'discount_type' => 'percentage',
                'discount_value' => 12,
                'max_discount' => 10000,
                'product_id' => $campaignProduct->id,
                'min_order' => 50000,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(10)->toDateString(),
            ],
            'idempotency_key' => 'phase9-e2e-campaign',
        ]);
        $campaignCreate->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.type', 'draft_campaign');
        $campaignActionId = (int) $campaignCreate->json('data.id');

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$campaignActionId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id);

        $campaignExecute = $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$campaignActionId}/execute");
        $campaignExecute->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.executed_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);
        $promotionId = $campaignExecute->json('data.execution_result.promotion_id');
        $this->assertIsInt($promotionId);
        $this->assertSame(1, Promotion::count());
        $this->assertSame($admin->id, Promotion::first()->created_by);

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$campaignActionId}/execute")
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result.promotion_id', $promotionId);
        $this->assertSame(1, Promotion::count());

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$campaignActionId}/reject", ['reason' => 'too late'])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        foreach ([$orderActionId, $campaignActionId] as $actionId) {
            $events = RecommendationActionEvent::where('recommendation_action_id', $actionId)
                ->orderBy('id')
                ->pluck('event_type')
                ->all();

            $this->assertSame(['created', 'approved', 'executed'], $events);
            $this->assertSame(1, RecommendationActionEvent::where('recommendation_action_id', $actionId)->where('event_type', 'approved')->count());
            $this->assertSame(1, RecommendationActionEvent::where('recommendation_action_id', $actionId)->where('event_type', 'executed')->count());
        }
    }
}
