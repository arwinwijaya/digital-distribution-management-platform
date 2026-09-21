<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full route enforcement: every protected route carries `rbac:<menu>:<level>`.
 *
 * Uses the default matrix seeded by the base TestCase.
 */
class RbacRouteEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return 'Bearer ' . app(AuthService::class)->createToken($user)['token'];
    }

    private function as(User $user): static
    {
        return $this->withHeader('Authorization', $this->tokenFor($user));
    }

    // ------------------------------------------------- read-level allowed

    public function test_finance_read_level_now_reaches_products(): void
    {
        // Default matrix: finance -> products = read (previously deny.finance).
        $this->as(User::factory()->finance()->create())
            ->getJson('/api/products')
            ->assertOk();
    }

    public function test_finance_read_level_now_reaches_marketplace(): void
    {
        // Default matrix: finance -> marketplace = read (previously deny.finance).
        $finance = User::factory()->finance()->create();

        $this->as($finance)->getJson('/api/marketplace/suppliers')->assertOk();
        $this->as($finance)->getJson('/api/marketplace/products')->assertOk();
    }

    public function test_finance_read_level_now_reaches_invoices_and_payments(): void
    {
        $finance = User::factory()->finance()->create();

        $this->as($finance)->getJson('/api/invoices')->assertOk();
        $this->as($finance)->getJson('/api/payments')->assertOk();
    }

    public function test_finance_stays_blocked_from_deliveries_by_controller(): void
    {
        // Matrix grants delivery:read, but DeliveryController::index() has a
        // stricter allowlist (admin/sales/driver) → still 403 for finance.
        $this->as(User::factory()->finance()->create())
            ->getJson('/api/deliveries')
            ->assertForbidden();
    }

    public function test_finance_stays_blocked_from_sales_visits_by_controller(): void
    {
        // Matrix grants sales:read, but SalesController::index() allowlists
        // admin/sales only → still 403 for finance.
        $this->as(User::factory()->finance()->create())
            ->getJson('/api/sales/visits')
            ->assertForbidden();
    }

    public function test_sales_can_read_products(): void
    {
        $this->as(User::factory()->sales()->create())
            ->getJson('/api/products')
            ->assertOk();
    }

    // ------------------------------------------------- none-level blocked

    public function test_finance_is_blocked_from_data_intelligence(): void
    {
        $this->as(User::factory()->finance()->create())
            ->getJson('/api/ai/recommendations')
            ->assertForbidden();
    }

    public function test_finance_is_blocked_from_admin_order_approval(): void
    {
        $this->as(User::factory()->finance()->create())
            ->putJson('/api/orders/1/approve')
            ->assertForbidden();
    }

    public function test_finance_read_cannot_edit_outlets(): void
    {
        // outlets: finance = read < edit.
        $this->as(User::factory()->finance()->create())
            ->postJson('/api/outlets', [])
            ->assertForbidden();
    }

    public function test_sales_cannot_reach_admin_promotions(): void
    {
        $this->as(User::factory()->sales()->create())
            ->getJson('/api/admin/promotions')
            ->assertForbidden();
    }

    public function test_driver_cannot_reach_analytics_dashboard(): void
    {
        $this->as(User::factory()->driver()->create())
            ->getJson('/api/analytics/dashboard')
            ->assertForbidden();
    }

    public function test_unauthenticated_is_401(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    // ------------------------------------------------- edit-level

    public function test_admin_can_reach_admin_promotions(): void
    {
        $this->as(User::factory()->admin()->create())
            ->getJson('/api/admin/promotions')
            ->assertOk();
    }

    public function test_admin_can_view_and_trigger_pipeline(): void
    {
        $admin = User::factory()->admin()->create();

        // K-C: the `rbac` gate is `data_intelligence:read` (admin holds read);
        // the real mutation authorization is the controller's isAdmin() guard.
        $this->as($admin)
            ->getJson('/api/admin/pipeline/status')
            ->assertOk();

        $this->as($admin)
            ->postJson('/api/admin/pipeline/manual-trigger')
            ->assertStatus(202);
    }

    public function test_finance_cannot_trigger_pipeline(): void
    {
        // data_intelligence = none for finance → blocked at the middleware.
        $this->as(User::factory()->finance()->create())
            ->postJson('/api/admin/pipeline/manual-trigger')
            ->assertForbidden();
    }

    // ------------------------------------------------- K-C route-mapping cases

    public function test_admin_can_manage_territories_under_read_gate(): void
    {
        // K-C: territory mutations are gated at `analytics:read` (admin holds
        // read); TerritoryController enforces the admin write boundary.
        $admin = User::factory()->admin()->create();

        $this->as($admin)
            ->postJson('/api/admin/territories', ['name' => 'Area Selatan'])
            ->assertCreated();
    }

    public function test_finance_cannot_manage_territories(): void
    {
        // analytics = none for finance → middleware blocks before the controller.
        $this->as(User::factory()->finance()->create())
            ->postJson('/api/admin/territories', ['name' => 'Area Barat'])
            ->assertForbidden();
    }

    public function test_outlet_can_self_register(): void
    {
        // K-C: self-service registration is authorized in StoreOutletRequest
        // (finance denied); the registering outlet holds only `read` on the
        // `outlets` menu, so no `rbac:outlets:edit` gate is applied.
        $this->as(User::factory()->outlet()->create())
            ->postJson('/api/outlets', [
                'name' => 'Toko Mandiri',
                'phone' => '081200000001',
                'address' => 'Jl. Merdeka 1',
                'city' => 'Jakarta',
                'district' => 'Menteng',
            ])
            ->assertCreated();
    }

    public function test_finance_cannot_self_register_outlet(): void
    {
        $this->as(User::factory()->finance()->create())
            ->postJson('/api/outlets', [])
            ->assertForbidden();
    }

    public function test_outlet_can_reach_ai_analytics(): void
    {
        // K-C: /ai/* is dual-audience; authorization lives in AIController.
        $outletUser = User::factory()->outlet()->create();
        \App\Models\Outlet::factory()->create(['user_id' => $outletUser->id]);

        $this->as($outletUser)
            ->getJson('/api/ai/recommendations')
            ->assertOk();
    }

    // ------------------------------------------------- deny.finance removal

    public function test_deny_finance_alias_and_class_are_gone(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $this->assertStringNotContainsString('deny.finance', $routes, 'routes/api.php still references deny.finance');

        $kernel = file_get_contents(app_path('Http/Kernel.php'));
        $this->assertStringNotContainsString('deny.finance', $kernel, 'Kernel.php still aliases deny.finance');

        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/DenyFinanceAdministration.php'),
            'DenyFinanceAdministration middleware class still exists',
        );
    }
}
