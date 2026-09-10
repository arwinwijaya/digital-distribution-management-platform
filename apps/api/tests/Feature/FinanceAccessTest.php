<?php

namespace Tests\Feature;

use App\Models\RoleAssignmentAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_finance(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'finance-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $target = User::factory()->outlet()->create();
        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('data.token');

        $this->withToken($token)
            ->postJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'finance',
        ]);
        $this->assertDatabaseHas('role_assignment_audits', [
            'target_user_id' => $target->id,
            'actor_user_id' => $admin->id,
            'from_role' => 'outlet',
            'to_role' => 'finance',
            'action' => RoleAssignmentAudit::ASSIGNED,
        ]);
    }

    public function test_finance_cannot_access_unrelated_administration(): void
    {
        $finance = User::factory()->create([
            'role' => 'finance',
            'email' => 'finance-unrelated@example.test',
            'password' => Hash::make('password'),
        ]);
        $token = $this->postJson('/api/auth/login', [
            'email' => $finance->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $requests = [
            fn () => $this->withHeaders($headers)->postJson('/api/outlets', []),
            fn () => $this->withHeaders($headers)->getJson('/api/products'),
            fn () => $this->withHeaders($headers)->getJson('/api/marketplace/suppliers'),
            fn () => $this->withHeaders($headers)->getJson('/api/marketplace/products'),
            fn () => $this->withHeaders($headers)->getJson('/api/credit-limit'),
            fn () => $this->withHeaders($headers)->getJson('/api/admin/outlets/1/credit-limit'),
            fn () => $this->withHeaders($headers)->getJson('/api/ai/recommendations'),
            fn () => $this->withHeaders($headers)->getJson('/api/sales/visits'),
            fn () => $this->withHeaders($headers)->getJson('/api/deliveries'),
            fn () => $this->withHeaders($headers)->getJson('/api/orders'),
            fn () => $this->withHeaders($headers)->putJson('/api/orders/1/approve'),
        ];

        foreach ($requests as $request) {
            $request()->assertForbidden();
        }
    }

    public function test_removed_finance_role_denies_old_token(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'finance-remove-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $target = User::factory()->create([
            'role' => 'finance',
            'email' => 'finance-target@example.test',
            'password' => Hash::make('password'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');
        $financeToken = $this->postJson('/api/auth/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->withToken($adminToken)
            ->deleteJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertOk();

        $this->withToken($financeToken)
            ->getJson('/api/finance/access')
            ->assertForbidden();
    }

    public function test_finance_replaces_prior_role(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'finance-replace-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $target = User::factory()->sales()->create();
        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->withToken($token)
            ->postJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'finance']);
        $this->assertDatabaseHas('role_assignment_audits', [
            'target_user_id' => $target->id,
            'from_role' => 'sales',
            'to_role' => 'finance',
            'action' => RoleAssignmentAudit::ASSIGNED,
        ]);
        $this->assertSame(1, RoleAssignmentAudit::where('target_user_id', $target->id)->count());
    }

    public function test_inactive_user_cannot_receive_finance(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'finance-inactive-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $target = User::factory()->outlet()->create(['is_active' => false]);
        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->withToken($token)
            ->postJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'outlet',
        ]);
        $this->assertDatabaseCount('role_assignment_audits', 0);
    }

    public function test_finance_assignment_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'finance-repeat-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $target = User::factory()->create(['role' => 'finance', 'name' => 'Finance User']);
        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');

        $this->withToken($token)
            ->postJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/admin/users/'.$target->id.'/finance-role')
            ->assertOk();

        $this->assertSame(0, RoleAssignmentAudit::where('target_user_id', $target->id)->count());
        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'role' => 'finance',
            'name' => 'Finance User',
        ]);
    }
}
