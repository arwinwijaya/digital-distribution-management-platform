<?php

namespace Tests\Feature;

use App\Models\MenuDefinition;
use App\Models\RoleMenuAccess;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /auth/me must include a full 23-key `rbac` map for the caller's role.
 */
class AuthMeRbacTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return 'Bearer ' . app(AuthService::class)->createToken($user)['token'];
    }

    public function test_me_includes_full_rbac_map(): void
    {
        $sales = User::factory()->sales()->create();

        $response = $this->withHeader('Authorization', $this->tokenFor($sales))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $rbac = $response->json('data.rbac');

        $this->assertIsArray($rbac);
        $this->assertCount(23, $rbac);
        $this->assertSame('read', $rbac['products']);
        $this->assertSame('edit', $rbac['orders']);
        $this->assertSame('none', $rbac['rbac_matrix']);
        $this->assertSame('none', $rbac['analytics']);
    }

    public function test_existing_me_fields_are_unchanged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withHeader('Authorization', $this->tokenFor($admin))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.name', $admin->name)
            ->assertJsonPath('data.email', $admin->email)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'created_at', 'rbac']]);
    }

    public function test_empty_database_yields_all_none_map(): void
    {
        // No seed: every key must still be present, all `none`.
        RoleMenuAccess::query()->delete();
        MenuDefinition::query()->delete();

        $finance = User::factory()->finance()->create();

        $rbac = $this->withHeader('Authorization', $this->tokenFor($finance))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->json('data.rbac');

        $this->assertCount(23, $rbac);
        $this->assertSame(['none'], array_values(array_unique($rbac)));
    }
}
