<?php

namespace Tests\Feature;

use App\Models\OperationalEvent;
use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 — T10: approve/execute replenishment plans with draft PO metadata.
 *
 * Behaviour:
 * - approve draft → approved + audit event
 * - execute before approve → 422
 * - approved execute → status executed + PO record per supplier (reference + items) + audit
 * - replay idempotent via logical_key
 * - unauthenticated 401, unauthorized 403 (rbac:supply_chain:edit)
 * - failure: insufficient/invalid items → status failed, no partial PO metadata, audit failure
 */
class ReplenishmentApproveExecuteTest extends TestCase
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

    private function outletUser(): User
    {
        return User::factory()->outlet()->create();
    }

    private function draftPlanWithItems(string $status = 'draft'): ReplenishmentPlan
    {
        $admin = $this->admin();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id]);

        $plan = new ReplenishmentPlan([
            'supplier_id' => $supplier->id,
            'created_by' => $admin->id,
            'status' => $status,
            'window_start' => now()->startOfMonth()->toDateString(),
            'window_end' => now()->endOfMonth()->toDateString(),
            'execution_result' => null,
        ]);
        $plan->save();

        $item = new ReplenishmentPlanItem([
            'replenishment_plan_id' => $plan->id,
            'product_id' => $product->id,
            'reorder_quantity' => 10,
            'data_sufficiency' => 'sufficient',
        ]);
        $item->save();

        return $plan->load('items.product');
    }

    // RED cycle 1 tests

    public function test_approve_draft_plan_successfully_updates_status_and_records_audit(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('draft');

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $user->id);

        $this->assertDatabaseHas('replenishment_plans', [
            'id' => $plan->id,
            'status' => 'approved',
            'approved_by' => $user->id,
        ]);

        // Append-only audit event via OperationalEventService
        $this->assertDatabaseHas('operational_events', [
            'actor_id' => $user->id,
            'action' => 'replenishment.approved',
            'outcome' => 'success',
        ]);
    }

    public function test_execute_before_approve_returns_422(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('draft');

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-1',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_execute_approved_plan_creates_po_metadata_and_audit(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-2',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.executed_by', $user->id);

        $plan->refresh();
        $this->assertSame('executed', $plan->status);
        $this->assertNotNull($plan->executed_at);
        $this->assertNotNull($plan->execution_result);
        $this->assertArrayHasKey('purchase_orders', $plan->execution_result);
        $this->assertIsArray($plan->execution_result['purchase_orders']);
        $this->assertCount(1, $plan->execution_result['purchase_orders']);

        $po = $plan->execution_result['purchase_orders'][0];
        $this->assertArrayHasKey('reference', $po);
        $this->assertArrayHasKey('items', $po);
        $this->assertIsArray($po['items']);
        $this->assertCount(1, $po['items']);
        $this->assertArrayHasKey('product_id', $po['items'][0]);
        $this->assertArrayHasKey('reorder_quantity', $po['items'][0]);

        // Append-only audit event
        $this->assertDatabaseHas('operational_events', [
            'actor_id' => $user->id,
            'action' => 'replenishment.executed',
            'outcome' => 'success',
        ]);
    }

    public function test_replay_with_same_logical_key_is_idempotent(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        $first = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-same',
            ]);
        $first->assertOk();
        $firstResult = $first->json('data.execution_result');

        $second = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-same',
            ]);
        $second->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result', $firstResult);

        // Only one audit event for executed
        $this->assertSame(
            1,
            OperationalEvent::where('actor_id', $user->id)
                ->where('action', 'replenishment.executed')
                ->where('outcome', 'success')
                ->count()
        );
    }

    public function test_execute_requires_idempotency_key(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Nothing executed
        $this->assertSame('approved', $plan->fresh()->status);
        $this->assertNull($plan->fresh()->execution_result);
    }

    public function test_replay_with_divergent_payload_is_rejected(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-diff',
            ])->assertOk();

        // Mutate the plan items so the same logical_key now maps to a
        // different payload. This must be rejected (idempotency conflict).
        $plan->items()->first()->update(['reorder_quantity' => 99]);

        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-diff',
            ])->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $plan = $this->draftPlanWithItems('draft');

        $response = $this->postJson("/api/admin/replenishment-plans/{$plan->id}/approve");

        $response->assertUnauthorized();
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $user = $this->outletUser();
        $plan = $this->draftPlanWithItems('draft');

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve");

        $response->assertForbidden();
    }

    public function test_audit_events_are_appended_on_transitions(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('draft');

        // Approve
        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/approve")
            ->assertOk();

        // Execute
        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-audit',
            ])->assertOk();

        $events = OperationalEvent::where('actor_id', $user->id)
            ->whereIn('action', ['replenishment.approved', 'replenishment.executed'])
            ->orderBy('occurred_at')
            ->get();

        $this->assertSame(2, $events->count());
        $this->assertSame('replenishment.approved', $events[0]->action);
        $this->assertSame('replenishment.executed', $events[1]->action);
        $this->assertSame('success', $events[0]->outcome);
        $this->assertSame('success', $events[1]->outcome);
    }

    /**
     * RED cycle 2: execute on approved plan with insufficient/invalid items.
     * Should set status 'failed', write no partial PO metadata, and audit replenishment.failed.
     */
    public function test_execute_fails_when_items_are_insufficient_or_invalid(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        // Mutate the existing item to be invalid: insufficient data_sufficiency.
        $plan->items()->first()->update(['data_sufficiency' => 'insufficient', 'reorder_quantity' => 0]);

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-fail',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'failed');

        $plan->refresh();
        $this->assertSame('failed', $plan->status);
        $this->assertNotNull($plan->executed_at);
        $this->assertNotNull($plan->execution_result);

        // No purchase_orders should exist (no partial PO metadata).
        $this->assertArrayNotHasKey('purchase_orders', $plan->execution_result);
        $this->assertArrayHasKey('error', $plan->execution_result);
        $this->assertArrayHasKey('invalid_items', $plan->execution_result);

        $this->assertDatabaseHas('operational_events', [
            'actor_id' => $user->id,
            'action' => 'replenishment.failed',
            'outcome' => 'success',
        ]);
    }

    /**
     * RED cycle 2: execute on approved plan with zero reorder_quantity.
     */
    public function test_execute_fails_when_items_have_zero_reorder_quantity(): void
    {
        $user = $this->admin();
        $plan = $this->draftPlanWithItems('approved');

        $plan->items()->first()->update(['data_sufficiency' => 'sufficient', 'reorder_quantity' => 0]);

        $response = $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/replenishment-plans/{$plan->id}/execute", [
                'logical_key' => 'test-key-zero-qty',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'failed');

        $plan->refresh();
        $this->assertSame('failed', $plan->status);
        $this->assertArrayNotHasKey('purchase_orders', $plan->execution_result);

        $this->assertDatabaseHas('operational_events', [
            'actor_id' => $user->id,
            'action' => 'replenishment.failed',
            'outcome' => 'success',
        ]);
    }
}