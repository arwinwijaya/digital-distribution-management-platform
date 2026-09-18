<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    private function insight(string $end = '2026-09-18'): array
    {
        return app(AnalyticsService::class)->insight(Carbon::parse($end));
    }

    private function order(Outlet $outlet, float $total, string $date, string $status = 'Delivered'): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => $status,
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'insight-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse($date),
            'updated_at' => Carbon::parse($date),
        ]);

        return $order->fresh();
    }

    private function payment(Order $order, float $amount, string $date, string $status = 'completed'): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'status' => $status,
            'idempotency_key' => 'insight-payment-'.uniqid(),
        ]);
        DB::table('payments')->where('id', $payment->id)->update([
            'created_at' => Carbon::parse($date),
            'updated_at' => Carbon::parse($date),
        ]);

        return $payment->fresh();
    }

    /**
     * RED CYCLE 1: window math + delta up/down/null/zero.
     */
    public function test_window_math_and_delta_calculation(): void
    {
        $outlet = Outlet::factory()->create();

        // previous window sales = 100.00, current window sales = 120.00
        $this->order($outlet, 100, '2026-08-01'); // previous (2026-07-21..2026-08-19)
        $this->order($outlet, 120, '2026-09-01'); // current  (2026-08-20..2026-09-18)

        $result = $this->insight('2026-09-18');

        $this->assertSame('2026-08-20', $result['comparison']['period']['start_date']);
        $this->assertSame('2026-09-18', $result['comparison']['period']['end_date']);
        $this->assertSame('2026-07-21', $result['comparison']['previous_period']['start_date']);
        $this->assertSame('2026-08-19', $result['comparison']['previous_period']['end_date']);

        $this->assertSame(20.0, $result['metrics_delta']['sales_total']['delta_percent']);
        $this->assertSame('up', $result['metrics_delta']['sales_total']['direction']);
    }

    public function test_delta_down_when_current_is_lower(): void
    {
        $outlet = Outlet::factory()->create();
        $this->order($outlet, 100, '2026-08-01');
        $this->order($outlet, 80, '2026-09-01');

        $delta = $this->insight('2026-09-18')['metrics_delta']['sales_total'];

        $this->assertSame(-20.0, $delta['delta_percent']);
        $this->assertSame('down', $delta['direction']);
    }

    public function test_delta_is_null_when_previous_baseline_is_zero(): void
    {
        $outlet = Outlet::factory()->create();
        $this->order($outlet, 50, '2026-09-01');

        $delta = $this->insight('2026-09-18')['metrics_delta']['sales_total'];

        $this->assertNull($delta['delta_percent']);
        $this->assertSame('neutral', $delta['direction']);
    }

    public function test_zero_change_is_zero_percent_neutral(): void
    {
        $outlet = Outlet::factory()->create();
        $this->order($outlet, 100, '2026-08-01');
        $this->order($outlet, 100, '2026-09-01');

        $delta = $this->insight('2026-09-18')['metrics_delta']['sales_total'];

        $this->assertSame(0.0, $delta['delta_percent']);
        $this->assertSame('neutral', $delta['direction']);
    }

    public function test_outlets_and_products_counts_have_no_delta(): void
    {
        Outlet::factory()->create(['is_active' => true]);

        $delta = $this->insight('2026-09-18')['metrics_delta'];

        $this->assertArrayHasKey('orders_total', $delta);
        $this->assertArrayHasKey('sales_total', $delta);
        $this->assertArrayHasKey('payments_total', $delta);
        $this->assertArrayHasKey('outstanding_total', $delta);
        $this->assertArrayNotHasKey('outlets_total', $delta);
        $this->assertArrayNotHasKey('products_total', $delta);
    }

    /**
     * RED CYCLE 2: excluded statuses + needs_attention qualification.
     */
    public function test_cancelled_orders_are_excluded_from_both_windows(): void
    {
        $outlet = Outlet::factory()->create();
        // Only a Cancelled order in the previous window (worth 1000.00).
        $this->order($outlet, 1000, '2026-08-01', 'Cancelled');
        // A live order in the current window.
        $this->order($outlet, 500, '2026-09-01', 'Paid');

        // If the cancelled order were counted, previous = 1000 → delta -50.0.
        // Excluding it leaves no previous baseline → null.
        $delta = $this->insight('2026-09-18')['metrics_delta']['sales_total'];

        $this->assertNull($delta['delta_percent']);
        $this->assertSame('neutral', $delta['direction']);
    }

    public function test_outlet_with_sales_decline_is_flagged(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'Declining Outlet']);
        $this->order($outlet, 1000, '2026-08-01', 'Paid');
        $this->order($outlet, 700, '2026-09-01', 'Paid');

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertCount(1, $attention);
        $this->assertSame('Declining Outlet', $attention[0]['outlet_name']);
        $this->assertSame('sales_decline', $attention[0]['reason']);
        $this->assertSame(-30.0, $attention[0]['delta_percent']);
    }

    public function test_exactly_twenty_percent_decline_is_included(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'Edge Outlet']);
        $this->order($outlet, 1000, '2026-08-01', 'Paid');
        $this->order($outlet, 800, '2026-09-01', 'Paid');

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertCount(1, $attention);
        $this->assertSame('sales_decline', $attention[0]['reason']);
        $this->assertSame(-20.0, $attention[0]['delta_percent']);
    }

    public function test_decline_just_below_threshold_is_excluded_on_unrounded_value(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'Almost Outlet']);
        $this->order($outlet, 1000, '2026-08-01', 'Paid');
        // (800.04 - 1000) / 1000 * 100 = -19.996% → rounds to -20.0 but is NOT >= 20%.
        $this->order($outlet, 800.04, '2026-09-01', 'Paid');

        $this->assertSame([], $this->insight('2026-09-18')['needs_attention']);
    }

    public function test_point_in_time_outstanding_older_than_window_is_flagged(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'Owing Outlet']);
        // Created 90 days before 2026-09-18 → 2026-06-20, outside both windows.
        $this->order($outlet, 5000, '2026-06-20', 'New');

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertCount(1, $attention);
        $this->assertSame('Owing Outlet', $attention[0]['outlet_name']);
        $this->assertSame('outstanding_risk', $attention[0]['reason']);
    }

    public function test_outlet_without_baseline_and_without_outstanding_is_excluded(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'New Outlet']);
        $this->order($outlet, 500, '2026-09-01', 'Paid');

        $this->assertSame([], $this->insight('2026-09-18')['needs_attention']);
    }

    /**
     * RED CYCLE 3: cap 5 + deterministic ordering + both-reasons collapse.
     */
    public function test_needs_attention_is_capped_and_declines_rank_first(): void
    {
        // 3 declining outlets
        foreach ([['Decline A', 1000, 650], ['Decline B', 1000, 700], ['Decline C', 1000, 750]] as [$name, $prev, $cur]) {
            $outlet = Outlet::factory()->create(['name' => $name]);
            $this->order($outlet, $prev, '2026-08-01', 'Paid');
            $this->order($outlet, $cur, '2026-09-01', 'Paid');
        }
        // 5 outlets with point-in-time outstanding (orders older than both windows)
        foreach ([900000, 500000, 400000, 300000, 200000] as $index => $amount) {
            $outlet = Outlet::factory()->create(['name' => 'Owe '.($index + 1)]);
            $this->order($outlet, $amount, '2026-06-20', 'New');
        }

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertCount(5, $attention);
        $this->assertSame(
            ['Decline A', 'Decline B', 'Decline C', 'Owe 1', 'Owe 2'],
            collect($attention)->pluck('outlet_name')->all()
        );
        $this->assertSame('sales_decline', $attention[0]['reason']);
        $this->assertSame('outstanding_risk', $attention[3]['reason']);
    }

    public function test_equal_severity_is_tie_broken_by_name_ascending(): void
    {
        // Beta created first; equal -25% severity means Alpha must still rank first.
        foreach (['Beta Outlet', 'Alpha Outlet'] as $name) {
            $outlet = Outlet::factory()->create(['name' => $name]);
            $this->order($outlet, 1000, '2026-08-01', 'Paid');
            $this->order($outlet, 750, '2026-09-01', 'Paid');
        }

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertSame(['Alpha Outlet', 'Beta Outlet'], collect($attention)->pluck('outlet_name')->all());
    }

    public function test_outlet_matching_both_reasons_appears_once_as_sales_decline(): void
    {
        $outlet = Outlet::factory()->create(['name' => 'Both Outlet']);
        $this->order($outlet, 1000, '2026-08-01', 'Paid');
        $this->order($outlet, 700, '2026-09-01', 'Paid');
        // Old unpaid order → point-in-time outstanding too.
        $this->order($outlet, 5000, '2026-06-20', 'New');

        $attention = $this->insight('2026-09-18')['needs_attention'];

        $this->assertCount(1, $attention);
        $this->assertSame('Both Outlet', $attention[0]['outlet_name']);
        $this->assertSame('sales_decline', $attention[0]['reason']);
    }

    /**
     * RED CYCLE 4: trend zero-fill + ranking total + has_more.
     */
    public function test_sales_trends_are_zero_filled_to_thirty_daily_buckets(): void
    {
        $outlet = Outlet::factory()->create();
        $this->order($outlet, 100, '2026-08-20', 'Paid');
        $this->order($outlet, 50, '2026-09-01', 'Paid');

        $trends = $this->insight('2026-09-18')['sales_trends'];

        $this->assertCount(30, $trends);
        $this->assertSame('2026-08-20', $trends[0]['period']);
        $this->assertSame('2026-09-18', $trends[29]['period']);

        $byPeriod = collect($trends)->keyBy('period');
        $this->assertSame(1, $byPeriod['2026-08-20']['orders_total']);
        $this->assertSame('100.00', $byPeriod['2026-08-20']['sales_total']);
        $this->assertSame(0, $byPeriod['2026-08-21']['orders_total']);
        $this->assertSame('0.00', $byPeriod['2026-08-21']['sales_total']);
        $this->assertSame('0.00', $byPeriod['2026-08-21']['payments_total']);
    }

    public function test_outlet_performance_total_and_has_more_reflect_all_outlets(): void
    {
        for ($index = 1; $index <= 15; $index++) {
            $outlet = Outlet::factory()->create(['name' => sprintf('Outlet %02d', $index)]);
            $this->order($outlet, 100 + $index, '2026-09-01', 'Paid');
        }

        $result = $this->insight('2026-09-18');

        $this->assertSame(15, $result['outlet_performance_total']);
        $this->assertTrue($result['outlet_performance_has_more']);
        $this->assertCount(10, $result['outlet_performance']);
    }

    public function test_outlet_performance_has_more_is_false_when_under_limit(): void
    {
        for ($index = 1; $index <= 7; $index++) {
            $outlet = Outlet::factory()->create(['name' => sprintf('Small %02d', $index)]);
            $this->order($outlet, 100 + $index, '2026-09-01', 'Paid');
        }

        $result = $this->insight('2026-09-18');

        $this->assertSame(7, $result['outlet_performance_total']);
        $this->assertFalse($result['outlet_performance_has_more']);
        $this->assertCount(7, $result['outlet_performance']);
    }
}
