<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockPlanningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'stock-plan-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $this->admin->email,
            'password' => 'password123',
        ])->json('data.token');

        $this->outlet = Outlet::factory()->create();
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    /**
     * Create an order with one item for the given product and backfill created_at.
     */
    protected function createOrderWithItem(
        Product $product,
        int $quantity,
        string $status,
        string $createdAt,
    ): Order {
        $order = Order::create([
            'order_id' => 'ORD-STK-' . uniqid(),
            'outlet_id' => $this->outlet->id,
            'status' => $status,
            'total_amount' => $quantity * $product->price,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'stk-test-' . uniqid(),
        ]);

        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'subtotal' => $quantity * $product->price,
        ]);

        return $order->fresh();
    }

    /**
     * Run the pipeline with the stock stage registered and return the run.
     */
    protected function runPipeline(): \App\Models\DataPipelineRun
    {
        $pipelineService = new \App\Services\DataPipelineService();
        $pipelineService->registerStage(
            'stock',
            (new StockPlanningService())->stageCallback()
        );

        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);
        $run = $pipelineService->run();
        Carbon::setTestNow();

        return $run;
    }

    // ------------------------------------------------------------------ //
    // Cycle 1 — SKU receives a reorder recommendation                     //
    // ------------------------------------------------------------------ //

    public function test_sku_receives_a_reorder_recommendation(): void
    {
        // Arrange: freeze time for deterministic 30-day window
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: supplier with lead_time_days = 5
        $supplier = Supplier::factory()->active()->create([
            'lead_time_days' => 5,
        ]);

        // Arrange: product with stock_quantity = 3, belonging to supplier
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        // Arrange: 60 total units from eligible orders in the 30-day window
        // (30 + 20 + 10 = 60)
        $this->createOrderWithItem($product, 30, 'Delivered', '2026-09-05');
        $this->createOrderWithItem($product, 20, 'New', '2026-09-08');
        $this->createOrderWithItem($product, 10, 'Confirmed', '2026-09-10');

        // Arrange: excluded-status orders must NOT contribute quantity
        $this->createOrderWithItem($product, 50, 'Cancelled', '2026-09-06');
        $this->createOrderWithItem($product, 30, 'Canceled', '2026-09-07');
        $this->createOrderWithItem($product, 20, 'Rejected', '2026-09-09');
        $this->createOrderWithItem($product, 10, 'Invalid', '2026-09-11');

        // Act: publish stock snapshot via the real pipeline
        $pipelineRun = $this->runPipeline();
        $this->assertSame('completed', $pipelineRun->status);

        Carbon::setTestNow();

        // Act: admin requests stock planning
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/stock-planning');

        $response->assertOk()->assertJsonPath('status', 'success');

        // Assert: find the payload for our product
        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);

        $stockData = null;
        foreach ($items as $item) {
            if (($item['product_id'] ?? null) === $product->id) {
                $stockData = $item;
                break;
            }
        }
        $this->assertNotNull($stockData, 'Our test product must appear in the response.');

        // Assert: demand = 60 units / 30 days = 2 units/day
        $this->assertSame(60, $stockData['total_demand']);
        $this->assertEqualsWithDelta(2.0, $stockData['average_daily_demand'], 0.001);

        // Assert: lead_time_demand = 2 × 5 = 10
        $this->assertEqualsWithDelta(10.0, $stockData['lead_time_demand'], 0.001);

        // Assert: reorder_quantity = max(0, 10 − 3) = 7
        $this->assertSame(7, $stockData['reorder_quantity']);

        // Assert: warning is present for stockout/reorder
        $this->assertTrue($stockData['has_warning'] ?? false, 'SKU should have a stockout/reorder warning.');

        // Assert: snapshot_version and window metadata from ActiveDataSnapshotReader
        $reader = new \App\Services\ActiveDataSnapshotReader();
        $activeVersion = $reader->version();
        $this->assertNotNull($activeVersion);
        $response->assertJsonPath('data.snapshot_version', $activeVersion);

        $window = $reader->window();
        $this->assertNotNull($window);
        $response->assertJsonPath('data.window.start', $window['start']);
        $response->assertJsonPath('data.window.end', $window['end']);
        $response->assertJsonPath('data.window.timezone', $window['timezone']);
    }

    // ------------------------------------------------------------------ //
    // Cycle 2 — sufficient stock or zero demand produces no reorder       //
    // ------------------------------------------------------------------ //

    public function test_sufficient_stock_or_zero_demand_produces_no_reorder(): void
    {
        // Arrange: freeze time for deterministic 30-day window
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        $supplier = Supplier::factory()->active()->create([
            'lead_time_days' => 5,
        ]);

        // Arrange: product A has sufficient stock (stock_quantity = 1000)
        $productA = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 1000,
            'is_active' => true,
        ]);
        $this->createOrderWithItem($productA, 10, 'Delivered', '2026-09-05');

        // Arrange: product B has zero demand (no order items)
        $productB = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        // Act: publish stock snapshot via the real pipeline
        $pipelineRun = $this->runPipeline();
        $this->assertSame('completed', $pipelineRun->status);

        Carbon::setTestNow();

        // Act: admin requests stock planning
        $response = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/stock-planning');

        $response->assertOk()->assertJsonPath('status', 'success');

        $items = $response->json('data.items');
        $this->assertIsArray($items);

        // Find payloads for products A and B
        $stockA = null;
        $stockB = null;
        foreach ($items as $item) {
            if (($item['product_id'] ?? null) === $productA->id) {
                $stockA = $item;
            }
            if (($item['product_id'] ?? null) === $productB->id) {
                $stockB = $item;
            }
        }

        $this->assertNotNull($stockA, 'Product A (sufficient stock) must appear in response.');
        $this->assertNotNull($stockB, 'Product B (zero demand) must appear in response.');

        // Assert: product A has sufficient stock → no reorder
        $this->assertSame(0, $stockA['reorder_quantity']);
        $this->assertFalse($stockA['has_warning'] ?? true, 'Sufficient stock must not trigger a warning.');
        $this->assertSame('ok', $stockA['status']);

        // Assert: product B has zero demand → no reorder
        $this->assertSame(0, $stockB['total_demand']);
        $this->assertSame(0, $stockB['reorder_quantity']);
        $this->assertFalse($stockB['has_warning'] ?? true, 'Zero demand must not trigger a warning.');
        $this->assertSame('ok', $stockB['status']);
    }
}
