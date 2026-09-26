<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecuteDraftCampaignTest extends TestCase
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

    private function draftCampaignAction(string $status = 'approved'): RecommendationAction
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        return RecommendationAction::factory()->create([
            'status' => $status,
            'type' => 'draft_campaign',
            'outlet_id' => $outlet->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'payload' => [
                'type' => 'draft_campaign',
                'outlet_id' => $outlet->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'campaign' => [
                    'name' => 'AI Campaign '.uniqid(),
                    'description' => 'From AI recommendation',
                    'discount_type' => 'percentage',
                    'discount_value' => 15,
                    'max_discount' => 10000,
                    'product_id' => $product->id,
                    'min_order' => 50000,
                    'start_date' => now()->addDay()->toDateString(),
                    'end_date' => now()->addMonth()->toDateString(),
                ],
            ],
            'idempotency_key' => 'execute-campaign-test-'.uniqid(),
        ]);
    }

    public function test_approved_draft_campaign_executes_creates_promotion_with_session_actor(): void
    {
        $admin = $this->admin();
        $action = $this->draftCampaignAction();
        $headers = ['Authorization' => $this->token($admin)];

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'executed');

        $promotionId = $action->fresh()->execution_result['promotion_id'];
        $this->assertSame(1, Promotion::count());
        $promo = Promotion::first();
        $this->assertSame($promotionId, $promo->id);
        $this->assertSame($admin->id, $promo->created_by); // Actor from session, not payload
    }

    public function test_execute_replay_does_not_duplicate_promotion(): void
    {
        $admin = $this->admin();
        $action = $this->draftCampaignAction();
        $headers = ['Authorization' => $this->token($admin)];

        $first = $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/execute");
        $first->assertOk();
        $firstPromoId = $first->json('data.execution_result.promotion_id');

        $second = $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/execute");
        $second->assertOk()
            ->assertJsonPath('data.idempotent_replay', true)
            ->assertJsonPath('data.execution_result.promotion_id', $firstPromoId);

        $this->assertSame(1, Promotion::count());
    }

    public function test_unapproved_status_returns_422(): void
    {
        $admin = $this->admin();
        $headers = ['Authorization' => $this->token($admin)];

        foreach (['draft', 'pending_approval', 'rejected', 'failed'] as $status) {
            $action = $this->draftCampaignAction($status);
            $this->withHeaders($headers)
                ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
                ->assertStatus(422)
                ->assertJsonPath('status', 'error');
        }

        $this->assertSame(0, Promotion::count());
    }

    public function test_overlap_rejection_marks_failed_no_partial_promo(): void
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();

        // Pre-existing overlapping active promo for same product & date range.
        Promotion::factory()->active()->forProduct($product)->create([
            'start_date' => now()->addDay(),
            'end_date' => now()->addMonth(),
        ]);

        $action = RecommendationAction::factory()->create([
            'status' => 'approved',
            'type' => 'draft_campaign',
            'outlet_id' => $outlet->id,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'payload' => [
                'type' => 'draft_campaign',
                'outlet_id' => $outlet->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                'campaign' => [
                    'name' => 'Overlapping Campaign',
                    'discount_type' => 'percentage',
                    'discount_value' => 10,
                    'product_id' => $product->id,
                    'min_order' => 0,
                    'start_date' => now()->addDay()->toDateString(),
                    'end_date' => now()->addMonth()->toDateString(),
                ],
            ],
            'idempotency_key' => 'execute-overlap-fail-'.uniqid(),
        ]);

        $this->withHeader('Authorization', $this->token($admin))
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame(1, Promotion::count()); // Only pre-existing
        $this->assertSame('failed', $action->fresh()->status);
        $this->assertNotNull($action->fresh()->execution_result['error'] ?? null);
    }

    public function test_actor_comes_from_session_not_payload(): void
    {
        $admin = $this->admin();
        $spoofed = User::factory()->admin()->create();
        $action = $this->draftCampaignAction();
        $headers = ['Authorization' => $this->token($admin)];

        // Spoof created_by in request body — should be ignored
        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute", ['created_by' => $spoofed->id])
            ->assertOk();

        $promo = Promotion::first();
        $this->assertSame($admin->id, $promo->created_by);
        $this->assertNotSame($spoofed->id, $promo->created_by);
    }

    public function test_non_admin_cannot_execute_campaign(): void
    {
        $user = User::factory()->outlet()->create();
        $action = $this->draftCampaignAction();

        $this->withHeader('Authorization', $this->token($user))
            ->postJson("/api/admin/recommendation-actions/{$action->id}/execute")
            ->assertForbidden();
    }
}