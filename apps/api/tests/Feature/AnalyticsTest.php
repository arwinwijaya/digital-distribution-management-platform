<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'analytics-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    protected function order(Outlet $outlet, float $total, string $date, string $status = 'Delivered'): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => $status,
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'analytics-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse($date),
            'updated_at' => Carbon::parse($date),
        ]);
        return $order->fresh();
    }

    protected function payment(Order $order, float $amount, string $date, string $status = 'completed'): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => $status,
            'idempotency_key' => 'analytics-payment-'.uniqid(),
        ]);
        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => Carbon::parse($date),
            'updated_at' => Carbon::parse($date),
        ]);
        if ($status === 'completed') {
            DB::table('orders')->where('id', $order->id)->update([
                'paid_amount' => (float) $order->paid_amount + $amount,
            ]);
        }
        return $payment->fresh();
    }

    /**
     * RED CYCLE 1: Given data exists, when viewing the dashboard, key metrics
     * are displayed through the public HTTP endpoint.
     */
    public function test_admin_can_view_dashboard_metrics(): void
    {
        $firstOutlet = Outlet::factory()->create(['name' => 'Alpha Outlet', 'is_active' => true]);
        $secondOutlet = Outlet::factory()->create(['name' => 'Beta Outlet', 'is_active' => true]);
        Outlet::factory()->create(['name' => 'Inactive Outlet', 'is_active' => false]);
        Product::factory()->count(2)->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $firstOrder = $this->order($firstOutlet, 100, '2025-09-01');
        $secondOrder = $this->order($secondOutlet, 50, '2025-09-02', 'New');
        $this->order($secondOutlet, 999, '2025-09-03', 'Cancelled');
        $this->payment($firstOrder, 40, '2025-09-02');
        $this->payment($secondOrder, 10, '2025-09-03', 'pending');

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?start_date=2025-09-01&end_date=2025-09-30');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.metrics.orders_total', 2)
            ->assertJsonPath('data.metrics.sales_total', '150.00')
            ->assertJsonPath('data.metrics.outlets_total', 2)
            ->assertJsonPath('data.metrics.products_total', 2)
            ->assertJsonPath('data.metrics.payments_total', '40.00')
            ->assertJsonPath('data.metrics.outstanding_total', '110.00')
            ->assertJsonStructure([
                'data' => ['metrics', 'sales_trends', 'outlet_performance'],
            ]);
    }

    public function test_sales_trends_support_daily_weekly_and_monthly_groups(): void
    {
        $outlet = Outlet::factory()->create();
        $first = $this->order($outlet, 100, '2025-09-01');
        $this->order($outlet, 50, '2025-09-02');
        $third = $this->order($outlet, 25, '2025-10-05');
        $this->payment($first, 20, '2025-09-01');
        $this->payment($third, 10, '2025-10-05');

        foreach ([
            ['daily', ['2025-09-01', '2025-09-02', '2025-10-05']],
            ['weekly', ['2025-09-01', '2025-09-29']],
            ['monthly', ['2025-09-01', '2025-10-01']],
        ] as [$group, $periods]) {
            $response = $this->withHeaders($this->adminHeaders())->getJson(
                "/api/analytics/dashboard?group={$group}&start_date=2025-09-01&end_date=2025-10-31"
            );

            $response->assertOk()->assertJsonPath('data.sales_trends.0.period', $periods[0]);
            $this->assertSame($periods, collect($response->json('data.sales_trends'))->pluck('period')->all());
        }

        $monthly = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?group=monthly&start_date=2025-09-01&end_date=2025-10-31');
        $monthly->assertJsonPath('data.sales_trends.0.sales_total', '150.00')
            ->assertJsonPath('data.sales_trends.1.sales_total', '25.00');
    }

    public function test_outlet_performance_is_ranked_by_sales_then_name_then_id(): void
    {
        $alpha = Outlet::factory()->create(['name' => 'Alpha Outlet']);
        $beta = Outlet::factory()->create(['name' => 'Beta Outlet']);
        $this->order($beta, 100, '2025-09-01');
        $this->order($alpha, 100, '2025-09-01');

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?start_date=2025-09-01&end_date=2025-09-30');

        $response->assertOk();
        $ranking = $response->json('data.outlet_performance');
        $this->assertSame(['Alpha Outlet', 'Beta Outlet'], collect($ranking)->pluck('outlet_name')->all());
        $this->assertSame([1, 2], collect($ranking)->pluck('rank')->all());
    }

    public function test_empty_dashboard_has_stable_zero_metrics_and_arrays(): void
    {
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?start_date=2025-09-01&end_date=2025-09-30');

        $response->assertOk()
            ->assertJsonPath('data.metrics.orders_total', 0)
            ->assertJsonPath('data.metrics.sales_total', '0.00')
            ->assertJsonPath('data.metrics.payments_total', '0.00')
            ->assertJsonPath('data.metrics.outstanding_total', '0.00')
            ->assertJsonPath('data.sales_trends', [])
            ->assertJsonPath('data.outlet_performance', []);
    }

    public function test_dashboard_requires_admin_and_rejects_unsafe_date_filters(): void
    {
        $outletUser = User::factory()->outlet()->create([
            'email' => 'analytics-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email,
            'password' => 'password123',
        ])->json('data.token');

        $this->getJson('/api/analytics/dashboard')->assertUnauthorized();
        $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->getJson('/api/analytics/dashboard')->assertForbidden();
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?group=yearly')->assertStatus(422);
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?start_date=2025-10-01&end_date=2025-09-01')
            ->assertStatus(422);
    }

    public function test_date_filters_exclude_orders_outside_requested_range(): void
    {
        $outlet = Outlet::factory()->create();
        $this->order($outlet, 25, '2025-08-31');
        $this->order($outlet, 100, '2025-09-10');

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/analytics/dashboard?start_date=2025-09-01&end_date=2025-09-30');

        $response->assertOk()->assertJsonPath('data.metrics.sales_total', '100.00');
    }
}
