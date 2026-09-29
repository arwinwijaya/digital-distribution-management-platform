<?php

namespace Tests\Performance;

use App\Models\DataSnapshotValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\ActiveDataSnapshotReader;
use App\Services\DataPipelineService;
use App\Services\GeographicAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase as ApiTestCase;

/**
 * Response-size guard + truncation metadata for the geographic endpoint.
 *
 * Given the ScaleFixtureSeeder data with a published v2 snapshot,
 * when GET /admin/analytics/geographic is requested,
 * then the serialized response is ≤500KB,
 * or meta.truncated=true with omitted_zero_days / product_summary_capped set and the cap applied.
 */
class GeographicScaleTest extends ApiTestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'geo-scale-admin@ddp.test',
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

    protected function createOutlet(array $overrides = []): Outlet
    {
        return Outlet::factory()->create($overrides);
    }

    protected function createOrder(Outlet $outlet, float $amount, string $createdAt): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => $amount,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'geo-scale-test-'.uniqid(),
        ]);
        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

        return $order->fresh();
    }

    protected function runPipeline(): \App\Models\DataPipelineRun
    {
        $pipelineService = new DataPipelineService();
        $pipelineService->registerStage(
            'geographic',
            (new GeographicAnalyticsService())->stageCallback()
        );

        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);
        $run = $pipelineService->run();
        Carbon::setTestNow();

        return $run;
    }

    /**
     * Test 1: Scale fixture response respects 500KB budget (size guard).
     *
     * Given ScaleFixtureSeeder data with a published v2 snapshot,
     * when the endpoint is called,
     * then response is ≤500KB or meta.truncated=true with cap metadata.
     */
    public function test_scale_fixture_response_respects_500kb_budget(): void
    {
        // Seed the reference scale fixture: 500 outlets (100 active), 5 suppliers,
        // 220 products, 500 orders.
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\ScaleFixtureSeeder',
            '--force' => true,
        ]);

        // Publish the geographic snapshot via the real pipeline.
        $this->runPipeline();

        // Act
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');

        // Assert
        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $json = $response->getContent();
        $responseSize = strlen($json);

        $data = $response->json('data');
        $truncated = $data['meta']['truncated'] ?? false;

        // Either the response fits within 500KB, or it was capped with proper metadata.
        $this->assertTrue(
            $responseSize <= 500 * 1024 || $truncated === true,
            "Response size {$responseSize} bytes exceeds 500KB and truncation was not applied. meta.truncated={$truncated}"
        );

        if ($truncated) {
            // When truncated, cap metadata must be present.
            $this->assertArrayHasKey('omitted_zero_days', $data['meta']);
            $this->assertArrayHasKey('product_summary_capped', $data['meta']);
        }
    }

    /**
     * Test 2: Simulated oversized response triggers truncation cap.
     *
     * Given a published snapshot with an oversized payload (simulated via
     * a reduced budget config),
     * when the endpoint is called,
     * then meta.truncated=true and the cap is applied.
     */
    public function test_oversized_response_triggers_truncation_cap(): void
    {
        // Create a large but valid dataset that would exceed a small budget.
        $activeOutlets = collect(range(1, 50))->map(function () {
            $user = User::factory()->outlet()->create();
            return Outlet::factory()->create([
                'user_id' => $user->id,
                'is_active' => true,
                'latitude' => -6.2 + (mt_rand(-10, 10) / 1000),
                'longitude' => 106.8 + (mt_rand(-10, 10) / 1000),
            ]);
        });
        Outlet::factory()->count(50)->create(['is_active' => false]);

        $products = Product::factory()->count(20)->create(['stock_quantity' => 100]);

        // Generate many orders to inflate daily_by_status.
        foreach (range(1, 300) as $number) {
            $outlet = $activeOutlets->random();
            $product = $products->random();
            $quantity = mt_rand(1, 5);
            $status = $number % 10 === 0 ? 'Delivered' : 'New';
            $order = Order::create([
                'order_id' => sprintf('SCALE-OVERSIZED-%06d', $number),
                'outlet_id' => $outlet->id,
                'status' => $status,
                'total_amount' => (float) $product->price * $quantity,
                'paid_amount' => 0,
                'commission_percentage' => 2,
                'idempotency_key' => 'geo-oversized-'.$number,
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'subtotal' => (float) $product->price * $quantity,
            ]);
        }

        $this->runPipeline();

        // Temporarily lower the budget to force truncation.
        config(['geographic.response_budget_bytes' => 2 * 1024]); // 2KB

        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/geographic');

        // Assert truncation was applied.
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.meta.truncated', true);

        $meta = $response->json('data.meta');
        $this->assertIsInt($meta['omitted_zero_days']);
        $this->assertGreaterThanOrEqual(0, $meta['omitted_zero_days']);
        $this->assertIsBool($meta['product_summary_capped']);

        $json = $response->getContent();
        $responseSize = strlen($json);

        // The response must now fit within the forced budget.
        $this->assertLessThanOrEqual(2 * 1024, $responseSize);
    }
}