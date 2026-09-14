<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\RecommendationEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeasurementTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(string $role = 'outlet'): array
    {
        $user = User::factory()->state(['role' => $role])->create([
            'password' => Hash::make('password123'),
        ]);
        $outlet = $role === 'outlet' ? Outlet::factory()->create(['user_id' => $user->id]) : null;
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.token');

        return [$user, $outlet, ['Authorization' => "Bearer {$token}"]];
    }

    private function order(Outlet $outlet, string $date, float $total = 100): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-MEAS-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'meas-'.uniqid(),
        ]);
        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        return $order->fresh();
    }

    // -------------------------------------------------------------- //
    // Cycle 1 — Recent-average fallback for sparse history             //
    // -------------------------------------------------------------- //

    public function test_recent_average_fallback_is_returned_for_sparse_history(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        [, $outlet, $headers] = $this->authenticatedUser();
        $product = Product::factory()->create(['stock_quantity' => 10, 'is_active' => true]);

        // Five orders across 7 distinct days — fewer than 30 but with positive observations.
        for ($day = 1; $day <= 5; $day++) {
            $date = "2026-09-0{$day}";
            $ord = $this->order($outlet, $date, $day * 50);
            OrderItem::create([
                'order_id' => $ord->id,
                'product_id' => $product->id,
                'quantity' => $day,
                'unit_price' => 50,
                'subtotal' => $day * 50,
            ]);
        }

        // Act: recommendation endpoint
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $recData = $response->json('data');

        // 30-day sparse metadata must be present.
        $this->assertArrayHasKey('days_in_window', $recData['data_sufficiency']);
        $this->assertArrayHasKey('window_days', $recData['data_sufficiency']);
        $this->assertArrayHasKey('recent_average_fallback', $recData['data_sufficiency']);
        $this->assertGreaterThan(0, $recData['data_sufficiency']['days_in_window']);
        $this->assertLessThan(30, $recData['data_sufficiency']['days_in_window']);
        $this->assertSame(30, $recData['data_sufficiency']['window_days']);
        $this->assertTrue($recData['data_sufficiency']['recent_average_fallback']);
        $this->assertNotEmpty($recData['recommendations']);

        // Act: forecast endpoint
        $forecast = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $forecast->assertOk()->assertJsonPath('status', 'success');
        $fcData = $forecast->json('data');

        $this->assertArrayHasKey('days_in_window', $fcData['data_sufficiency']);
        $this->assertArrayHasKey('recent_average_fallback', $fcData['data_sufficiency']);
        $this->assertGreaterThan(0, $fcData['data_sufficiency']['days_in_window']);
        $this->assertTrue($fcData['data_sufficiency']['recent_average_fallback']);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 2 — Empty history returns safe output                      //
    // -------------------------------------------------------------- //

    public function test_empty_history_returns_safe_output(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        [, , $headers] = $this->authenticatedUser();

        // Act: recommendation endpoint with zero eligible history
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $recData = $response->json('data');

        $this->assertSame([], $recData['recommendations']);
        $this->assertTrue($recData['fallback']);
        $this->assertSame('insufficient', $recData['data_sufficiency']['level']);
        $this->assertSame(0, $recData['data_sufficiency']['days_in_window']);
        $this->assertSame(30, $recData['data_sufficiency']['window_days']);
        $this->assertFalse($recData['data_sufficiency']['recent_average_fallback']);
        $this->assertArrayNotHasKey('accuracy', $recData);
        $this->assertArrayNotHasKey('confidence_score', $recData);
        $this->assertFalse($recData['measurement']['measured']);

        // Act: forecast endpoint with zero eligible history
        $forecast = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $forecast->assertOk()->assertJsonPath('status', 'success');
        $fcData = $forecast->json('data');

        $this->assertSame([], $fcData['predictions']);
        $this->assertTrue($fcData['fallback']);
        $this->assertSame('insufficient', $fcData['data_sufficiency']['level']);
        $this->assertSame(0, $fcData['data_sufficiency']['days_in_window']);
        $this->assertFalse($fcData['data_sufficiency']['recent_average_fallback']);
        $this->assertArrayNotHasKey('accuracy', $fcData);
        $this->assertArrayNotHasKey('confidence_score', $fcData);
        $this->assertFalse($fcData['measurement']['measured']);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 3 — Recommendation funnel is measured (writer-expected RED)  //
    // -------------------------------------------------------------- //
    public function test_recommendation_funnel_is_measured(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $admin = User::factory()->admin()->create([
            'email' => 'meas-funnel-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 50, 'is_active' => true]);
        $otherProduct = Product::factory()->create(['stock_quantity' => 50, 'is_active' => true]);
        $otherOutlet = Outlet::factory()->create();

        // 30-day window is 2026-08-15 .. 2026-09-13 inclusive.
        // Seed funnel events inside the window:
        // displayed x4, clicked x3, cart x2
        $eventDates = ['2026-08-20', '2026-08-25', '2026-09-01', '2026-09-10'];
        foreach (['displayed', 'displayed', 'displayed', 'displayed'] as $i => $type) {
            RecommendationEvent::create([
                'event_uuid' => 'funnel-disp-'.$i.'-'.uniqid(),
                'event_type' => 'displayed',
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'occurred_at' => $eventDates[$i % 4].' 10:00:00',
                'metadata' => null,
            ]);
        }
        foreach (['clicked','clicked','clicked'] as $i => $type) {
            RecommendationEvent::create([
                'event_uuid' => 'funnel-click-'.$i.'-'.uniqid(),
                'event_type' => 'clicked',
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'occurred_at' => $eventDates[$i % 4].' 11:00:00',
                'metadata' => null,
            ]);
        }
        foreach ([0,1] as $i) {
            RecommendationEvent::create([
                'event_uuid' => 'funnel-cart-'.$i.'-'.uniqid(),
                'event_type' => 'cart',
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'occurred_at' => $eventDates[$i].' 12:00:00',
                'metadata' => null,
            ]);
        }
        // Discriminators: events OUTSIDE the 30-day window must NOT inflate funnel
        RecommendationEvent::create([
            'event_uuid' => 'funnel-disp-other-'.uniqid(),
            'event_type' => 'displayed',
            'outlet_id' => $otherOutlet->id,
            'product_id' => $otherProduct->id,
            'occurred_at' => '2026-08-10 10:00:00',
            'metadata' => null,
        ]);

        // Successful, non-excluded order whose (product_id,outlet_id) matches the displayed events.
        $purchasedOrder = Order::create([
            'order_id' => 'ORD-FUNNEL-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 200,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'funnel-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $purchasedOrder->id)->update([
            'created_at' => Carbon::parse('2026-08-28 10:00:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-08-28 10:00:00', 'Asia/Jakarta'),
        ]);
        OrderItem::create([
            'order_id' => $purchasedOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'subtotal' => 200,
        ]);
        // Excluded order with matching pair — must NOT count as purchased.
        $excludedOrder = Order::create([
            'order_id' => 'ORD-FUNNEL-EX-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Cancelled',
            'total_amount' => 100,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'funnel-ex-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $excludedOrder->id)->update([
            'created_at' => Carbon::parse('2026-08-29 10:00:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-08-29 10:00:00', 'Asia/Jakarta'),
        ]);
        OrderItem::create([
            'order_id' => $excludedOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'subtotal' => 100,
        ]);
        // Order whose product_id does NOT match any funnel event — must NOT count.
        $mismatchOrder = Order::create([
            'order_id' => 'ORD-FUNNEL-MM-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 100,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'funnel-mm-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $mismatchOrder->id)->update([
            'created_at' => Carbon::parse('2026-08-30 10:00:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-08-30 10:00:00', 'Asia/Jakarta'),
        ]);
        OrderItem::create([
            'order_id' => $mismatchOrder->id,
            'product_id' => $otherProduct->id,
            'quantity' => 3,
            'unit_price' => 10,
            'subtotal' => 30,
        ]);

        // Publish measurement snapshot through the real pipeline.
        $pipelineService = new \App\Services\DataPipelineService();
        $pipelineService->registerStage('measurement', (new \App\Services\MeasurementService())->stageCallback());
        $run = $pipelineService->run();
        $this->assertSame('completed', $run->status);

        // Act: admin requests recommendation measurement through the ActiveDataSnapshotReader-backed endpoint.
        $response = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $data = $response->json('data');

        // snapshot_version + window metadata from the active snapshot
        $this->assertArrayHasKey('snapshot_version', $data);
        $this->assertSame($run->fresh()->snapshot->version ?? $run->lineage['snapshot_version'] ?? $data['snapshot_version'], $data['snapshot_version']);
        $this->assertArrayHasKey('window', $data);
        $this->assertSame('Asia/Jakarta', $data['window']['timezone']);

        // funnel in strict order: displayed → clicked → cart → purchased
        $this->assertArrayHasKey('funnel', $data);
        $steps = array_column($data['funnel'], 'step');
        $this->assertSame(['displayed', 'clicked', 'cart', 'purchased'], $steps);
        $counts = array_column($data['funnel'], 'count', 'step');
        $this->assertSame(4, $counts['displayed']);
        $this->assertSame(3, $counts['clicked']);
        $this->assertSame(2, $counts['cart']);
        // purchased = exactly 1 (only the Delivered matching order; excluded + mismatch excluded)
        $this->assertSame(1, $counts['purchased']);

        // safe conversion rates (zero denominator never divides)
        $this->assertArrayHasKey('rates', $data);
        $this->assertArrayHasKey('attribution', $data);
        // rates are fractions/percents — assert they are numeric and bounded
        foreach (['clicked_rate','cart_rate','purchased_rate'] as $k) {
            $this->assertArrayHasKey($k, $data['rates']);
            $this->assertIsNumeric($data['rates'][$k]);
            $this->assertGreaterThanOrEqual(0, (float) $data['rates'][$k]);
            $this->assertLessThanOrEqual(1, (float) $data['rates'][$k]);
        }

        // no campaign attribution is inferred
        // campaign-inference boundary: no campaign key is present
        $this->assertArrayNotHasKey('campaign', $data);
        $this->assertArrayNotHasKey('campaign_attribution', $data);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 4 — Duplicate event retry is idempotent                    //
    // -------------------------------------------------------------- //

    public function test_duplicate_event_retry_is_idempotent(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $admin = User::factory()->admin()->create([
            'email' => 'meas-idempotent-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 50, 'is_active' => true]);

        $eventUuid = 'event-uuid-'.Str::uuid()->toString();

        // First ingestion — should return 201 Created.
        $first = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => $eventUuid,
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ]);
        $first->assertCreated()->assertJsonPath('status', 'success');
        $firstId = $first->json('data.id');
        $this->assertNotNull($firstId);

        // Second ingestion with identical payload — should be idempotent (200 OK) and not create a new row.
        $second = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => $eventUuid,
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ]);
        $second->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.idempotent_replay', true);
        $this->assertSame($firstId, $second->json('data.id'));

        // The count of displayed events for this UUID must remain 1.
        $this->assertSame(1, RecommendationEvent::where('event_uuid', $eventUuid)->count());

        // Publish snapshot and verify the funnel count is exactly 1, not inflated.
        $pipelineService = new \App\Services\DataPipelineService();
        $pipelineService->registerStage('measurement', (new \App\Services\MeasurementService())->stageCallback());
        $run = $pipelineService->run();
        $this->assertSame('completed', $run->status);

        $response = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $counts = array_column($response->json('data.funnel'), 'count', 'step');
        $this->assertSame(1, $counts['displayed']);

        // Conflicting reuse (same UUID, different event_type) — must return 409 Conflict.
        $conflict = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => $eventUuid,
            'event_type' => 'clicked',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ]);
        $conflict->assertStatus(409)->assertJsonPath('status', 'error');

        // Same UUID, different product_id — must also return 409 Conflict.
        $otherProduct = Product::factory()->create(['stock_quantity' => 50, 'is_active' => true]);
        $conflict2 = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => $eventUuid,
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $otherProduct->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ]);
        $conflict2->assertStatus(409)->assertJsonPath('status', 'error');

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 5 — Forecast WAPE + strict target boundary                //
    // -------------------------------------------------------------- //

    public function test_forecast_wape_and_strict_target_boundary_are_calculated(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $admin = User::factory()->admin()->create([
            'email' => 'meas-wape-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        // WAPE fixture `pass` (20% WAPE → strictly >70% accuracy, target achieved).
        // 10 days @ actual=100 forecast=120 each + 10 @ 10/12 + 10 @ 1/1.2
        // absolute 2*100 + 2*10 + 0.2*10 = 222 ; actual 1110 ; WAPE 222/1110 = 0.2
        $passPairs = array_merge(
            array_fill(0, 10, ['actual' => '100.00', 'forecast' => '120.00']),
            array_fill(0, 10, ['actual' => '10.00', 'forecast' => '12.00']),
            array_fill(0, 10, ['actual' => '1.00', 'forecast' => '1.20']),
        );
        DB::table('forecast_actuals')->insert([
            'dimension_key' => 'fixture:wape-pass',
            'pairs' => json_encode($passPairs),
        ]);

        // WAPE fixture `tight-fail` (30% WAPE → accuracy 70% exactly, NOT strictly >70%, target not achieved).
        $failPairs = array_fill(0, 30, ['actual' => '100.00', 'forecast' => '130.00']);
        DB::table('forecast_actuals')->insert([
            'dimension_key' => 'fixture:wape-tight-fail',
            'pairs' => json_encode($failPairs),
        ]);

        $pipelineService = new \App\Services\DataPipelineService();
        $pipelineService->registerStage('measurement', (new \App\Services\MeasurementService())->stageCallback());
        $run = $pipelineService->run();
        $this->assertSame('completed', $run->status);

        // Pass fixture: WAPE 0.2000, target achieved (accuracy 80% strictly >70%).
        $pass = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/forecasts?fixture=fixture:wape-pass');
        $pass->assertOk()->assertJsonPath('status', 'success');
        $pData = $pass->json('data');
        $this->assertSame('target_achieved', $pData['status']);
        $this->assertTrue($pData['target_achieved']);
        $this->assertSame(30, $pData['actual_days']);
        $this->assertSame('0.2000', $pData['wape']);
        $this->assertEqualsWithDelta(0.80, (float) $pData['accuracy'], 0.0001);
        $this->assertEqualsWithDelta(80.0, (float) $pData['accuracy_percent'], 0.01);
        $this->assertSame($run->fresh()->snapshot->version, $pData['snapshot_version']);
        $this->assertArrayHasKey('window', $pData);

        // Tight-fail fixture: WAPE 0.3000, target NOT achieved (strict boundary).
        $tight = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/forecasts?fixture=fixture:wape-tight-fail');
        $tight->assertOk()->assertJsonPath('status', 'success');
        $tData = $tight->json('data');
        $this->assertSame('target_not_achieved', $tData['status']);
        $this->assertFalse($tData['target_achieved']);
        $this->assertSame('0.3000', $tData['wape']);
        $this->assertEqualsWithDelta(0.70, (float) $tData['accuracy'], 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $tData['accuracy_percent'], 0.01);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 6 — All-zero / insufficient actuals remain pending        //
    // -------------------------------------------------------------- //

    public function test_all_zero_or_insufficient_actuals_remain_pending(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $admin = User::factory()->admin()->create([
            'email' => 'meas-pending-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        // Fixture 1: all-zero actuals (30 days) — must be pending, not perfect 0.0 WAPE.
        $zeroPairs = array_fill(0, 30, ['actual' => '0.00', 'forecast' => '0.00']);
        DB::table('forecast_actuals')->insert([
            'dimension_key' => 'fixture:wape-all-zero',
            'pairs' => json_encode($zeroPairs),
        ]);

        // Fixture 2: insufficient actuals (only 10 days) — must be insufficient-data.
        $shortPairs = array_fill(0, 10, ['actual' => '100.00', 'forecast' => '100.00']);
        DB::table('forecast_actuals')->insert([
            'dimension_key' => 'fixture:wape-insufficient',
            'pairs' => json_encode($shortPairs),
        ]);

        $pipelineService = new \App\Services\DataPipelineService();
        $pipelineService->registerStage('measurement', (new \App\Services\MeasurementService())->stageCallback());
        $run = $pipelineService->run();
        $this->assertSame('completed', $run->status);

        $allZero = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/forecasts?fixture=fixture:wape-all-zero');
        $allZero->assertOk()->assertJsonPath('status', 'success');
        $zData = $allZero->json('data');
        $this->assertSame('pending', $zData['status']);
        $this->assertSame(30, $zData['actual_days']);
        $this->assertNull($zData['wape']);
        $this->assertFalse($zData['target_achieved']);

        $short = $this->withHeaders($adminHeaders)->getJson('/api/admin/analytics/measurement/forecasts?fixture=fixture:wape-insufficient');
        $short->assertOk()->assertJsonPath('status', 'success');
        $sData = $short->json('data');
        $this->assertSame('insufficient-data', $sData['status']);
        $this->assertSame(10, $sData['actual_days']);
        $this->assertNull($sData['wape']);
        $this->assertFalse($sData['target_achieved']);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 7 — Legacy AI access remains compatible                  //
    // -------------------------------------------------------------- //

    public function test_legacy_ai_access_remains_compatible(): void
    {
        [, $outlet, $outletHeaders] = $this->authenticatedUser();
        $product = Product::factory()->create(['stock_quantity' => 10, 'is_active' => true]);
        $ord = $this->order($outlet, '2026-09-01', 200);
        OrderItem::create([
            'order_id' => $ord->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 50,
            'subtotal' => 200,
        ]);

        $this->withHeaders($outletHeaders)->getJson('/api/ai/recommendations')
            ->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonPath('data.recommendations.0.product_id', $product->id)
            ->assertJsonStructure(['data' => ['recommendations', 'limit', 'method', 'data_points', 'data_sufficiency', 'measurement']]);
        $this->withHeaders($outletHeaders)->getJson('/api/ai/forecast?period=daily&horizon=2')
            ->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['predictions', 'period', 'horizon', 'method', 'data_sufficiency', 'measurement']]);
        $this->withHeaders($outletHeaders)->getJson('/api/ai/segmentation')
            ->assertOk()->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['segment', 'confidence', 'signals', 'method', 'measurement']]);

        [, $otherOutlet] = $this->authenticatedUser();
        $this->withHeaders($outletHeaders)->getJson('/api/ai/recommendations?outlet_id='.$otherOutlet->id)->assertForbidden();

        $this->getJson('/api/admin/analytics/measurement/recommendations')->assertForbidden();
        $this->getJson('/api/admin/analytics/measurement/forecasts')->assertForbidden();
        $this->postJson('/api/admin/measurement/events', [
            'event_uuid' => 'anon-'.uniqid(),
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ])->assertForbidden();

        $this->withHeaders($outletHeaders)->getJson('/api/admin/analytics/measurement/recommendations')
            ->assertForbidden()->assertJsonPath('status', 'error');
        $this->withHeaders($outletHeaders)->getJson('/api/admin/analytics/measurement/forecasts')
            ->assertForbidden()->assertJsonPath('status', 'error');
        $this->withHeaders($outletHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => 'outlet-'.uniqid(),
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ])->assertStatus(403)->assertJsonPath('status', 'error');
    }

    // -------------------------------------------------------------- //
    // Cycle 8 — Measurement event ingestion is admin-only            //
    // -------------------------------------------------------------- //

    public function test_measurement_event_ingestion_is_admin_only(): void
    {
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 50, 'is_active' => true]);

        $admin = User::factory()->admin()->create([
            'email' => 'meas-ingest-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        [, , $outletHeaders] = $this->authenticatedUser();

        $payload = [
            'event_uuid' => 'ingest-admin-'.Str::uuid()->toString(),
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'occurred_at' => '2026-09-10 10:00:00',
        ];

        $this->postJson('/api/admin/measurement/events', $payload)->assertUnauthorized();
        $this->withHeaders($outletHeaders)->postJson('/api/admin/measurement/events', $payload)
            ->assertStatus(403)->assertJsonPath('status', 'error');

        $first = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', $payload);
        $first->assertCreated()->assertJsonPath('status', 'success')->assertJsonPath('data.event_uuid', $payload['event_uuid']);
        $firstId = $first->json('data.id');
        $this->assertNotNull($firstId);

        $replay = $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', $payload);
        $replay->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('data.idempotent_replay', true);
        $this->assertSame($firstId, $replay->json('data.id'));
        $this->assertSame(1, RecommendationEvent::where('event_uuid', $payload['event_uuid'])->count());

        $conflicting = array_merge($payload, ['event_type' => 'clicked']);
        $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', $conflicting)
            ->assertStatus(409)->assertJsonPath('status', 'error');

        $this->withHeaders($adminHeaders)->postJson('/api/admin/measurement/events', [
            'event_uuid' => 'partial-'.uniqid(),
        ])->assertStatus(422);
    }
}
