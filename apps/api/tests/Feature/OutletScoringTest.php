<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Services\OutletScoringService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GWT: Given outlet with 0 orders, When score calculated, Then score=0
     */
    public function test_outlet_with_no_orders_has_score_zero(): void
    {
        $outlet = Outlet::factory()->create(['score' => 0]);

        $service = app(OutletScoringService::class);
        $score = $service->recalculate($outlet);

        $this->assertEquals(0, $score);
        $this->assertEquals(0, $outlet->fresh()->score);
    }

    /**
     * GWT: Given outlet with Delivered orders, When score calculated, Then volume*0.4 + frequency*0.3 + recency*0.3
     */
    public function test_outlet_scoring_formula_with_delivered_orders(): void
    {
        $outlet = Outlet::factory()->create(['score' => 0]);

        // 5 delivered orders spread in last 7 days
        for ($i = 1; $i <= 5; $i++) {
            Order::create([
                'order_id' => "ORD-SCORE-{$i}",
                'outlet_id' => $outlet->id,
                'status' => 'Delivered',
                'total_amount' => 100000,
                'idempotency_key' => "score-key-{$i}",
            ]);
        }

        $service = app(OutletScoringService::class);
        $score = $service->recalculate($outlet);

        // volume=5/100*100=5, 5*0.4=2
        // frequency: 5 orders / 12.857 weeks ≈0.388/wk -> scale 3.88 *0.3≈1.16
        // recency: last order today -> 100*0.3=30
        // = ~33
        $this->assertGreaterThan(20, $score);
        $this->assertLessThanOrEqual(100, $score);
        $this->assertEquals($score, $outlet->fresh()->score);

        // Verify deterministic formula
        $count90 = 5;
        $weeks = OutletScoringService::WINDOW_DAYS / 7.0;
        $ordersPerWeek = $count90 / $weeks;
        $capped = min(OutletScoringService::FREQUENCY_CAP_PER_WEEK, $ordersPerWeek);
        $frequency = ($capped / OutletScoringService::FREQUENCY_CAP_PER_WEEK) * 100.0;
        $volume = min(100.0, ($count90 / OutletScoringService::VOLUME_MAX) * 100.0);
        $recency = 100.0; // today
        $expected = (int) round(($volume * 0.4) + ($frequency * 0.3) + ($recency * 0.3));
        $this->assertEquals($expected, $score);
    }

    /**
     * GWT: Given sales-collected order (sales_user_id set), When scoring, Then counts same as self-serve
     */
    public function test_scoring_counts_sales_collected_orders_same_as_self_serve(): void
    {
        $outlet = Outlet::factory()->create(['score' => 0]);
        $sales = \App\Models\User::factory()->sales()->create();

        Order::create([
            'order_id' => 'ORD-SCORE-SALES-001',
            'outlet_id' => $outlet->id,
            'sales_user_id' => $sales->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'idempotency_key' => 'score-sales-1',
        ]);
        Order::create([
            'order_id' => 'ORD-SCORE-SELF-002',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'idempotency_key' => 'score-self-2',
        ]);

        $outlet2 = Outlet::factory()->create(['score' => 0]);
        Order::create([
            'order_id' => 'ORD-SCORE-SELF-003',
            'outlet_id' => $outlet2->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'idempotency_key' => 'score-self-3',
        ]);
        Order::create([
            'order_id' => 'ORD-SCORE-SELF-004',
            'outlet_id' => $outlet2->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'idempotency_key' => 'score-self-4',
        ]);

        $service = app(OutletScoringService::class);
        $score1 = $service->recalculate($outlet);
        $score2 = $service->recalculate($outlet2);

        // Both outlets have 2 Delivered orders in last 90 days, so same score
        $this->assertEquals($score2, $score1);
    }

    /**
     * GWT: Given outlet with only non-Delivered orders, When scoring, Then score=0
     */
    public function test_outlet_with_only_pending_orders_has_score_zero(): void
    {
        $outlet = Outlet::factory()->create(['score' => 10]);

        Order::create([
            'order_id' => 'ORD-PENDING-001',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 100000,
            'idempotency_key' => 'pending-1',
        ]);
        Order::create([
            'order_id' => 'ORD-PENDING-002',
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'pending-2',
        ]);

        $service = app(OutletScoringService::class);
        $score = $service->recalculate($outlet);

        $this->assertEquals(0, $score);
        $this->assertEquals(0, $outlet->fresh()->score);
    }

    /**
     * GWT: Given order older than 90 days, When scoring, Then not counted
     */
    public function test_recalculate_ignores_orders_older_than_90_days(): void
    {
        $outlet = Outlet::factory()->create(['score' => 0]);

        // Order older than 90 days - should not count
        $oldOrder = Order::create([
            'order_id' => 'ORD-OLD-001',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'idempotency_key' => 'old-1',
        ]);
        $oldOrder->created_at = CarbonImmutable::now()->subDays(100);
        $oldOrder->save();

        $service = app(OutletScoringService::class);
        $score = $service->recalculate($outlet);

        $this->assertEquals(0, $score);
    }
}
