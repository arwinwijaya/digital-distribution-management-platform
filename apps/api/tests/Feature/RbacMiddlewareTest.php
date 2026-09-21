<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rbac route middleware.
 *
 * Uses test-only routes so the assertions are independent of the real route
 * annotations added in T6. The default matrix is seeded by the base TestCase.
 */
class RbacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:api', 'rbac:products:read'])
            ->get('/api/_test/rbac-read', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:api', 'rbac:products:edit'])
            ->post('/api/_test/rbac-edit', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:api', 'rbac:analytics:read'])
            ->get('/api/_test/rbac-analytics', fn () => response()->json(['ok' => true]));

        // Laravel-idiomatic comma form must behave identically to the colon form.
        Route::middleware(['auth:api', 'rbac:products,read'])
            ->get('/api/_test/rbac-comma-read', fn () => response()->json(['ok' => true]));

        // Misconfigured level must fail closed, never grant access.
        Route::middleware(['auth:api', 'rbac:products:bogus'])
            ->get('/api/_test/rbac-bogus-level', fn () => response()->json(['ok' => true]));
    }

    private function bearerFor(User $user): string
    {
        return 'Bearer ' . app(AuthService::class)->createToken($user)['token'];
    }

    public function test_read_level_passes_read_route(): void
    {
        // sales -> products = read
        $sales = User::factory()->sales()->create();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/_test/rbac-read')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_read_level_is_blocked_on_edit_route(): void
    {
        // sales -> products = read, so POST (edit) must be forbidden.
        $sales = User::factory()->sales()->create();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/_test/rbac-edit')
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    public function test_edit_level_passes_edit_route(): void
    {
        // admin -> products = edit
        $admin = User::factory()->admin()->create();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/_test/rbac-edit')
            ->assertOk();
    }

    public function test_missing_row_is_treated_as_none(): void
    {
        // finance has no row for analytics -> none.
        $finance = User::factory()->finance()->create();

        $this->withHeader('Authorization', $this->bearerFor($finance))
            ->getJson('/api/_test/rbac-analytics')
            ->assertForbidden();
    }

    public function test_unauthenticated_is_401_before_rbac(): void
    {
        $this->getJson('/api/_test/rbac-read')->assertUnauthorized();
    }

    public function test_comma_separated_form_behaves_like_colon_form(): void
    {
        $sales = User::factory()->sales()->create();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/_test/rbac-comma-read')
            ->assertOk();
    }

    public function test_unknown_required_level_fails_closed(): void
    {
        $owner = User::factory()->platformOwner()->create();

        $this->withHeader('Authorization', $this->bearerFor($owner))
            ->getJson('/api/_test/rbac-bogus-level')
            ->assertForbidden();
    }

    public function test_forbidden_message_names_menu_and_level(): void
    {
        $sales = User::factory()->sales()->create();

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/_test/rbac-edit');

        $response->assertForbidden();
        $this->assertStringContainsString('products', $response->json('message'));
        $this->assertStringContainsString('edit', $response->json('message'));
    }
}
