<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\SalesTarget;
use App\Models\Territory;
use App\Models\User;
use App\Services\SalesPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPerformanceTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function bearerFor(User $user): string
    {
        $token = app(\App\Services\AuthService::class)->createToken($user)['token'];

        return 'Bearer ' . $token;
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create(['is_active' => true]);
    }

    private function makeSalesUser(?int $territoryId = null): User
    {
        return User::factory()->sales()->create([
            'is_active'    => true,
            'territory_id' => $territoryId,
        ]);
    }

    private function makeOutlet(): Outlet
    {
        return Outlet::factory()->create();
    }

    private function createOrder(
        int $outletId,
        int $salesUserId,
        string $status,
        float $totalAmount,
        ?\DateTimeInterface $createdAt = null,
    ): Order {
        $order = Order::create([
            'order_id'          => 'ORD-PERF-' . uniqid(),
            'outlet_id'         => $outletId,
            'sales_user_id'     => $salesUserId,
            'status'            => $status,
            'total_amount'      => $totalAmount,
            'idempotency_key'   => 'perf-key-' . uniqid(),
        ]);
        if ($createdAt !== null) {
            $order->created_at = $createdAt;
            $order->save();
        }

        return $order;
    }

    private function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    private function lastMonthPeriod(): string
    {
        return now()->subMonth()->format('Y-m');
    }

    // =================================================================
    // Sales target CRUD
    // =================================================================

    public function test_admin_can_create_sales_target(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/sales-targets', [
                'user_id'       => $sales->id,
                'period'        => $this->currentPeriod(),
                'target_amount' => 50000000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $sales->id)
            ->assertJsonPath('data.period', $this->currentPeriod())
            ->assertJsonPath('data.target_amount', '50000000.00');

        $this->assertDatabaseHas('sales_targets', [
            'user_id'       => $sales->id,
            'period'        => $this->currentPeriod(),
            'target_amount' => 50000000,
        ]);
    }

    public function test_admin_can_list_sales_targets_with_limit_plus_one(): void
    {
        $admin = $this->makeAdmin();
        $s1 = $this->makeSalesUser();
        $s2 = $this->makeSalesUser();

        SalesTarget::create(['user_id' => $s1->id, 'period' => '2026-01', 'target_amount' => 1000]);
        SalesTarget::create(['user_id' => $s2->id, 'period' => '2026-01', 'target_amount' => 2000]);

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson('/api/admin/sales-targets?limit=1');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('next_cursor', SalesTarget::orderBy('id')->first()->id);
    }

    public function test_admin_can_view_sales_target(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();
        $target = SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $this->currentPeriod(),
            'target_amount' => 75000000,
        ]);

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales-targets/{$target->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.user_id', $sales->id);
    }

    public function test_admin_can_update_sales_target(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();
        $target = SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $this->currentPeriod(),
            'target_amount' => 10000000,
        ]);

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->patchJson("/api/admin/sales-targets/{$target->id}", [
                'target_amount' => 99000000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.target_amount', '99000000.00');

        $this->assertDatabaseHas('sales_targets', [
            'id'            => $target->id,
            'target_amount' => 99000000,
        ]);
    }

    public function test_admin_can_delete_sales_target(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();
        $target = SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $this->currentPeriod(),
            'target_amount' => 10000000,
        ]);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->deleteJson("/api/admin/sales-targets/{$target->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sales_targets', ['id' => $target->id]);
    }

    // =================================================================
    // Authorization rules
    // =================================================================

    public function test_sales_user_cannot_create_sales_target(): void
    {
        $sales = $this->makeSalesUser();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->postJson('/api/admin/sales-targets', [
                'user_id'       => $sales->id,
                'period'        => $this->currentPeriod(),
                'target_amount' => 100000,
            ])
            ->assertStatus(403);
    }

    public function test_sales_user_cannot_list_sales_targets(): void
    {
        $sales = $this->makeSalesUser();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/admin/sales-targets')
            ->assertStatus(403);
    }

    public function test_outlet_user_cannot_create_sales_target(): void
    {
        $outletUser = User::factory()->outlet()->create(['is_active' => true]);

        $this->withHeader('Authorization', $this->bearerFor($outletUser))
            ->postJson('/api/admin/sales-targets', [
                'user_id'       => $outletUser->id,
                'period'        => $this->currentPeriod(),
                'target_amount' => 100000,
            ])
            ->assertStatus(403);
    }

    // =================================================================
    // Validation edge cases
    // =================================================================

    public function test_duplicate_target_for_same_user_and_period_rejected(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();
        $period = $this->currentPeriod();

        SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $period,
            'target_amount' => 100000,
        ]);

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/sales-targets', [
                'user_id'       => $sales->id,
                'period'        => $period,
                'target_amount' => 200000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    public function test_invalid_period_format_rejected(): void
    {
        $admin = $this->makeAdmin();
        $sales = $this->makeSalesUser();

        $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson('/api/admin/sales-targets', [
                'user_id'       => $sales->id,
                'period'        => '2026/09',
                'target_amount' => 100000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);
    }

    // =================================================================
    // Performance calculation
    // =================================================================

    /**
     * GWT: Given sales with monthly target=50M, When current month orders
     * total=30M (Confirmed), Then achievement=60%.
     */
    public function test_achievement_percentage_of_target(): void
    {
        $service = app(SalesPerformanceService::class);
        $period = $this->currentPeriod();
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $period,
            'target_amount' => 50000000,
        ]);

        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 15000000);
        $this->createOrder($outlet->id, $sales->id, 'Delivered', 15000000);

        $perf = $service->calculatePerformance($sales->id, $period);

        $this->assertEqualsWithDelta(50000000.0, $perf['target'], 0.01);
        $this->assertEqualsWithDelta(30000000.0, $perf['achievement'], 0.01);
        $this->assertEqualsWithDelta(60.0, $perf['percentage'], 0.01);
        $this->assertSame(2, $perf['order_count']);
    }

    /**
     * GWT: Given sales with target=0, When calculating achievement,
     * Then 0% (no division by zero).
     */
    public function test_zero_target_yields_zero_percentage_no_division_by_zero(): void
    {
        $service = app(SalesPerformanceService::class);
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 5000000);

        $perf = $service->calculatePerformance($sales->id, $this->currentPeriod());

        $this->assertEqualsWithDelta(0.0, $perf['target'], 0.01);
        $this->assertEqualsWithDelta(5000000.0, $perf['achievement'], 0.01);
        $this->assertEqualsWithDelta(0.0, $perf['percentage'], 0.01);
        $this->assertSame(1, $perf['order_count']);
    }

    /**
     * GWT: Given cancelled order, When calculating achievement, Then excluded.
     */
    public function test_cancelled_orders_excluded_from_achievement(): void
    {
        $service = app(SalesPerformanceService::class);
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $this->currentPeriod(),
            'target_amount' => 10000000,
        ]);

        $this->createOrder($outlet->id, $sales->id, 'Delivered', 5000000);
        $this->createOrder($outlet->id, $sales->id, 'Cancelled', 3000000);

        $perf = $service->calculatePerformance($sales->id, $this->currentPeriod());

        $this->assertEqualsWithDelta(5000000.0, $perf['achievement'], 0.01);
        $this->assertEqualsWithDelta(50.0, $perf['percentage'], 0.01);
        $this->assertSame(1, $perf['order_count']);
    }

    /**
     * New orders must not count toward achievement.
     */
    public function test_new_orders_excluded_from_achievement(): void
    {
        $service = app(SalesPerformanceService::class);
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        $this->createOrder($outlet->id, $sales->id, 'New', 8000000);
        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 2000000);

        $perf = $service->calculatePerformance($sales->id, $this->currentPeriod());

        $this->assertEqualsWithDelta(2000000.0, $perf['achievement'], 0.01);
        $this->assertSame(1, $perf['order_count']);
    }

    /**
     * Orders created outside the queried period must not count.
     */
    public function test_orders_outside_period_are_excluded(): void
    {
        $service = app(SalesPerformanceService::class);
        $period = $this->currentPeriod();
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $period,
            'target_amount' => 10000000,
        ]);

        // Order in current month
        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 10000000);
        // Order in last month
        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 5000000, now()->subMonth());

        $perf = $service->calculatePerformance($sales->id, $period);

        $this->assertEqualsWithDelta(10000000.0, $perf['achievement'], 0.01);
        $this->assertSame(1, $perf['order_count']);
    }

    /**
     * All four revenue statuses contribute to achievement.
     */
    public function test_performance_service_counts_all_revenue_statuses(): void
    {
        $service = app(SalesPerformanceService::class);
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();

        $this->createOrder($outlet->id, $sales->id, 'Confirmed',        1000000);
        $this->createOrder($outlet->id, $sales->id, 'Delivered',        2000000);
        $this->createOrder($outlet->id, $sales->id, 'Paid',             3000000);
        $this->createOrder($outlet->id, $sales->id, 'Partially Paid',   4000000);

        $perf = $service->calculatePerformance($sales->id, $this->currentPeriod());

        $this->assertEqualsWithDelta(10000000.0, $perf['achievement'], 0.01);
        $this->assertSame(4, $perf['order_count']);
    }

    // =================================================================
    // My performance endpoint
    // =================================================================

    /**
     * GWT: Given sales user, When GET /sales/my-performance, Then own data only.
     */
    public function test_sales_user_my_performance_returns_own_data(): void
    {
        $sales = $this->makeSalesUser();
        $outlet = $this->makeOutlet();
        $period = $this->currentPeriod();

        SalesTarget::create([
            'user_id'       => $sales->id,
            'period'        => $period,
            'target_amount' => 20000000,
        ]);
        $this->createOrder($outlet->id, $sales->id, 'Confirmed', 8000000);

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/my-performance');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $sales->id)
            ->assertJsonPath('data.period', $period)
            ->assertJsonPath('data.target', '20000000.00')
            ->assertJsonPath('data.achievement', '8000000.00')
            ->assertJsonPath('data.percentage', '40.00')
            ->assertJsonPath('data.order_count', 1);
    }

    public function test_my_performance_defaults_to_current_period(): void
    {
        $sales = $this->makeSalesUser();

        $response = $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/sales/my-performance');

        $response->assertOk()
            ->assertJsonPath('data.period', $this->currentPeriod());
    }

    public function test_sales_user_cannot_access_my_performance_without_sales_role(): void
    {
        $outletUser = User::factory()->outlet()->create(['is_active' => true]);

        $this->withHeader('Authorization', $this->bearerFor($outletUser))
            ->getJson('/api/sales/my-performance')
            ->assertStatus(403);
    }

    // =================================================================
    // Admin performance endpoint
    // =================================================================

    /**
     * GWT: Given admin, When GET /admin/sales/performance, Then all sales
     * users with target, achievement, percentage.
     */
    public function test_admin_performance_returns_all_sales_users(): void
    {
        $admin = $this->makeAdmin();
        $s1 = $this->makeSalesUser();
        $s2 = $this->makeSalesUser();
        $outlet = $this->makeOutlet();
        $period = $this->currentPeriod();

        SalesTarget::create([
            'user_id'       => $s1->id,
            'period'        => $period,
            'target_amount' => 30000000,
        ]);
        SalesTarget::create([
            'user_id'       => $s2->id,
            'period'        => $period,
            'target_amount' => 60000000,
        ]);

        $this->createOrder($outlet->id, $s1->id, 'Delivered', 12000000);
        $this->createOrder($outlet->id, $s2->id, 'Confirmed', 30000000);

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(2, 'data');

        $users = collect($response->json('data'));
        $s1Perf = $users->firstWhere('user_id', $s1->id);
        $s2Perf = $users->firstWhere('user_id', $s2->id);

        $this->assertNotNull($s1Perf);
        $this->assertNotNull($s2Perf);
        $this->assertEqualsWithDelta(40.0, $s1Perf['percentage'], 0.01);
        $this->assertEqualsWithDelta(50.0, $s2Perf['percentage'], 0.01);
    }

    /**
     * GWT: Given sales user, When GET /admin/sales/performance, Then 403.
     */
    public function test_sales_user_cannot_access_admin_performance(): void
    {
        $sales = $this->makeSalesUser();

        $this->withHeader('Authorization', $this->bearerFor($sales))
            ->getJson('/api/admin/sales/performance')
            ->assertStatus(403);
    }

    public function test_admin_performance_with_limit_plus_one_pagination(): void
    {
        $admin = $this->makeAdmin();
        $period = $this->currentPeriod();

        // Create 3 sales users with targets
        for ($i = 0; $i < 3; $i++) {
            $s = $this->makeSalesUser();
            SalesTarget::create([
                'user_id'       => $s->id,
                'period'        => $period,
                'target_amount' => 10000000 * ($i + 1),
            ]);
        }

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}&limit=2");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.limit', 2);
    }

    /**
     * GWT: Given sales users, When GET /admin/sales/performance?limit=100&cursor=0,
     * Then meta.total counts ALL sales users (not just the page), meta.cursor is
     * the offset, and the legacy id-based next_cursor key is gone.
     */
    public function test_admin_performance_meta_total_and_cursor_offset(): void
    {
        $admin = $this->makeAdmin();
        $period = $this->currentPeriod();

        User::factory()->sales()->create(['name' => 'Alpha Sales', 'is_active' => true]);
        User::factory()->sales()->create(['name' => 'Bravo Sales', 'is_active' => true]);
        User::factory()->sales()->create(['name' => 'Charlie Sales', 'is_active' => true]);

        // Non-sales users must NOT count toward total.
        $this->makeAdmin();
        User::factory()->outlet()->create(['is_active' => true]);

        $response = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}&limit=100&cursor=0");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.limit', 100)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonMissingPath('next_cursor');
    }

    /**
     * GWT: Given 4 sales users, When paging with cursor=2, Then the second page is
     * selected by OFFSET into the same ordering (not `where id > 2`).
     */
    public function test_admin_performance_cursor_is_offset_not_id(): void
    {
        $admin = $this->makeAdmin();
        $period = $this->currentPeriod();

        User::factory()->sales()->create(['name' => 'Alpha Sales', 'is_active' => true]);
        User::factory()->sales()->create(['name' => 'Bravo Sales', 'is_active' => true]);
        User::factory()->sales()->create(['name' => 'Charlie Sales', 'is_active' => true]);
        User::factory()->sales()->create(['name' => 'Delta Sales', 'is_active' => true]);

        $all = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}&limit=100");
        $all->assertOk()->assertJsonCount(4, 'data');
        $allNames = collect($all->json('data'))->pluck('name')->all();

        $first = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}&limit=2&cursor=0");
        $first->assertOk()
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.has_more', true);
        $firstNameList = collect($first->json('data'))->pluck('name')->all();

        $second = $this->withHeader('Authorization', $this->bearerFor($admin))
            ->getJson("/api/admin/sales/performance?period={$period}&limit=2&cursor=2");
        $second->assertOk()
            ->assertJsonPath('meta.cursor', 2)
            ->assertJsonPath('meta.has_more', false);
        $secondNameList = collect($second->json('data'))->pluck('name')->all();

        $this->assertSame(array_slice($allNames, 0, 2), $firstNameList);
        $this->assertSame(array_slice($allNames, 2, 2), $secondNameList);
        $this->assertSame([], array_values(array_intersect($firstNameList, $secondNameList)));
    }
}
