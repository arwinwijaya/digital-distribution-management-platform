<?php

namespace Tests\Feature;

use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationActionApprovalTest extends TestCase
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

    private function action(string $status = 'draft'): RecommendationAction
    {
        return RecommendationAction::factory()->create([
            'status' => $status,
            'payload' => ['type' => 'draft_order', 'outlet_id' => 1, 'items' => []],
        ]);
    }

    public function test_admin_can_approve_draft_and_audit_actor_is_session_user(): void
    {
        $admin = $this->admin();
        $action = $this->action();

        $this->withHeader('Authorization', $this->token($admin))
            ->postJson("/api/admin/recommendation-actions/{$action->id}/approve", [
                'approved_by' => User::factory()->admin()->create()->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', $admin->id);

        $this->assertDatabaseHas('recommendation_actions', [
            'id' => $action->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('recommendation_action_events', [
            'recommendation_action_id' => $action->id,
            'event_type' => 'approved',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_reject_requires_reason_and_is_idempotent(): void
    {
        $admin = $this->admin();
        $action = $this->action();
        $headers = ['Authorization' => $this->token($admin)];

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/reject", [])
            ->assertStatus(422);

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/reject", ['reason' => 'Not needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Not needed');

        $this->withHeaders($headers)
            ->postJson("/api/admin/recommendation-actions/{$action->id}/reject", ['reason' => 'different'])
            ->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);

        $this->assertSame(1, RecommendationActionEvent::where('recommendation_action_id', $action->id)->where('event_type', 'rejected')->count());
    }

    public function test_approve_replay_does_not_duplicate_audit_and_illegal_transitions_are_422(): void
    {
        $admin = $this->admin();
        $headers = ['Authorization' => $this->token($admin)];
        $action = $this->action();

        $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/approve")->assertOk();
        $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/approve", ['approved_by' => 999])->assertOk()
            ->assertJsonPath('data.idempotent_replay', true);
        $this->assertSame(1, RecommendationActionEvent::where('recommendation_action_id', $action->id)->where('event_type', 'approved')->count());

        foreach (['rejected', 'executed'] as $status) {
            $invalid = $this->action($status);
            $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$invalid->id}/approve")->assertStatus(422);
        }
    }

    public function test_non_admin_cannot_approve_or_reject(): void
    {
        $user = User::factory()->outlet()->create();
        $action = $this->action();
        $headers = ['Authorization' => $this->token($user)];

        $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/approve")->assertForbidden();
        $this->withHeaders($headers)->postJson("/api/admin/recommendation-actions/{$action->id}/reject", ['reason' => 'no'])->assertForbidden();
    }
}
