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
use Illuminate\Support\Facades\DB;
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

    public function test_replenishment_approve_execute_is_idempotent_audited_and_admin_only_via_http(): void
    {
        $admin = $this->admin();
        $nonAdmin = $this->nonAdmin();
        $headers = ['Authorization' => $this->bearerFor($admin)];
        $nonAdminHeaders = ['Authorization' => $this->bearerFor($nonAdmin)];

        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 200, 'price' => 4500]);
        $plan = ReplenishmentPlan::factory()->create([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'status' => 'draft',
        ]);
        ReplenishmentPlanItem::factory()->create([
            'replenishment_plan_id' => $plan->id,
            'product_id' => $product->id,
            'reorder_quantity' => 50,
            'data_sufficiency' => 'sufficient',
        ]);

        $this->withHeaders($headers)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", ['logical_key' => 'phase9-repl-e2e'])
            ->assertStatus(422)->assertJsonPath('status', 'error');
        $this->withHeaders($nonAdminHeaders)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve")
            ->assertForbidden();

        $this->withHeaders($headers)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);
        $this->withHeaders($headers)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve")
            ->assertOk()->assertJsonPath('data.idempotent_replay', true);

        $execute = $this->withHeaders($headers)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", ['logical_key' => 'phase9-repl-e2e']);
        $execute->assertOk()->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.executed_by', $admin->id)
            ->assertJsonPath('data.idempotent_replay', false);
        $result = $execute->json('data.execution_result');
        $this->assertCount(1, $result['purchase_orders']);
        $this->assertSame($supplier->id, $result['purchase_orders'][0]['supplier_id']);
        $this->assertSame($product->id, $result['purchase_orders'][0]['items'][0]['product_id']);

        $this->withHeaders($nonAdminHeaders)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", ['logical_key' => 'phase9-repl-e2e'])
            ->assertForbidden();
        $this->withHeaders($headers)
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", ['logical_key' => 'phase9-repl-e2e'])
            ->assertOk()->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result', $result);

        $events = OperationalEvent::where('route', "replenishment-plans/{$plan->id}")
            ->orderBy('id')->pluck('action')->all();
        $this->assertSame(['replenishment.approved', 'replenishment.executed'], $events);
    }

    public function test_calibration_and_revenue_lift_persist_contracts_without_false_zero_or_perfect_claim(): void
    {
        DB::table('forecast_actuals')->updateOrInsert(
            ['dimension_key' => 'phase9-e2e-dim-a'],
            ['pairs' => json_encode([
                ['actual' => 10000, 'forecast' => 9500],
                ['actual' => 12000, 'forecast' => 11000],
            ])]
        );
        $cal1 = app(\App\Services\ForecastCalibrationService::class)->calibrate('phase9-e2e-dim-a');
        $this->assertSame(1.0, $cal1['bias_factor']);
        $this->assertSame(\App\Services\ForecastCalibrationService::METHOD_VERSION, $cal1['method_version']);
        $this->assertTrue($cal1['fallback']);
        $this->assertSame(2, $cal1['sample_size']);

        DB::table('forecast_actuals')->updateOrInsert(
            ['dimension_key' => 'phase9-e2e-dim-b'],
            ['pairs' => json_encode([
                ['actual' => 10000, 'forecast' => 9500],
                ['actual' => 12000, 'forecast' => 11000],
                ['actual' => 8000, 'forecast' => 7500],
            ])]
        );
        $cal2 = app(\App\Services\ForecastCalibrationService::class)->calibrate('phase9-e2e-dim-b');
        $this->assertGreaterThan(0.0, $cal2['bias_factor']);
        $this->assertSame(\App\Services\ForecastCalibrationService::METHOD_VERSION, $cal2['method_version']);
        $this->assertFalse($cal2['fallback']);
        $this->assertSame(3, $cal2['sample_size']);

        $applied = app(\App\Services\ForecastCalibrationService::class)->apply(['forecast_sales' => '10000.00'], 'phase9-e2e-dim-b');
        $this->assertSame(\App\Services\ForecastCalibrationService::METHOD_VERSION, $applied['method_version']);
        $this->assertFalse($applied['calibration']['fallback']);
        $this->assertNotSame('0.00', $applied['forecast_sales']);

        $experiment = \App\Models\AbExperiment::factory()->create(['name' => 'Phase9 E2E Lift Test', 'status' => 'running', 'minimum_sample_size' => 5]);
        $lift1 = app(\App\Services\RevenueLiftService::class)->computeLift($experiment, [
            'control' => [['revenue' => 1000.00], ['revenue' => 2000.00]],
            'treatment' => [['revenue' => 1200.00], ['revenue' => 2200.00]],
        ]);
        $this->assertSame('insufficient-data', $lift1->status);
        $this->assertNull($lift1->uplift);
        $this->assertSame(\App\Services\RevenueLiftService::METHOD_VERSION, $lift1->method_version);

        $experiment2 = \App\Models\AbExperiment::factory()->create(['name' => 'Phase9 E2E Lift Zero Control', 'status' => 'running', 'minimum_sample_size' => 2]);
        $lift2 = app(\App\Services\RevenueLiftService::class)->computeLift($experiment2, [
            'control' => [['revenue' => 0.00], ['revenue' => 0.00]],
            'treatment' => [['revenue' => 5000.00], ['revenue' => 6000.00]],
        ]);
        $this->assertSame('insufficient-data', $lift2->status);
        $this->assertNull($lift2->uplift);

        $experiment3 = \App\Models\AbExperiment::factory()->create(['name' => 'Phase9 E2E Lift Computed', 'status' => 'running', 'minimum_sample_size' => 3]);
        $lift3 = app(\App\Services\RevenueLiftService::class)->computeLift($experiment3, [
            'control' => [['revenue' => 10000.00], ['revenue' => 10000.00], ['revenue' => 10000.00]],
            'treatment' => [['revenue' => 11000.00], ['revenue' => 11000.00], ['revenue' => 11000.00]],
        ]);
        $this->assertSame('computed', $lift3->status);
        $this->assertEquals(0.1, (float) $lift3->uplift);
        $this->assertSame(\App\Services\RevenueLiftService::METHOD_VERSION, $lift3->method_version);
    }
}
