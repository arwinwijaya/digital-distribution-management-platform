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

    // ------------------------------------------------------------------ //
    // Cycle 3 — invalid stock-planning input is safe                      //
    // ------------------------------------------------------------------ //

    public function test_invalid_stock_planning_input_is_safe(): void
    {
        // Arrange: freeze time for deterministic 30-day window
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        // Arrange: supplier with valid lead_time for product A
        $supplierValid = Supplier::factory()->active()->create([
            'lead_time_days' => 5,
        ]);

        // Arrange: supplier with null lead_time for product B
        $supplierNullLead = Supplier::factory()->active()->create([
            'lead_time_days' => null,
        ]);

        // Arrange: supplier with negative lead_time for product C
        $supplierNegativeLead = Supplier::factory()->active()->create([
            'lead_time_days' => -3,
        ]);

        // Arrange: product A has negative stock (must be clamped to zero)
        $productNegativeStock = Product::factory()->create([
            'supplier_id' => $supplierValid->id,
            'stock_quantity' => -5,
            'is_active' => true,
        ]);
        $this->createOrderWithItem($productNegativeStock, 30, 'Delivered', '2026-09-05');

        // Arrange: product B has null lead time
        $productNullLead = Product::factory()->create([
            'supplier_id' => $supplierNullLead->id,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);
        $this->createOrderWithItem($productNullLead, 30, 'Delivered', '2026-09-06');

        // Arrange: product C has negative lead time
        $productNegLead = Product::factory()->create([
            'supplier_id' => $supplierNegativeLead->id,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);
        $this->createOrderWithItem($productNegLead, 30, 'Delivered', '2026-09-07');

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

        // Find payloads
        $stockNegative = null;
        $stockNullLead = null;
        $stockNegLead = null;
        foreach ($items as $item) {
            if (($item['product_id'] ?? null) === $productNegativeStock->id) {
                $stockNegative = $item;
            }
            if (($item['product_id'] ?? null) === $productNullLead->id) {
                $stockNullLead = $item;
            }
            if (($item['product_id'] ?? null) === $productNegLead->id) {
                $stockNegLead = $item;
            }
        }

        // Assert: negative stock clamped to zero; formula uses 0 instead of -5
        // demand = 30 / 30 = 1, lead_time_demand = 1 × 5 = 5, reorder = max(0, 5 - 0) = 5
        $this->assertNotNull($stockNegative, 'Product with negative stock must appear in response.');
        $this->assertSame(0, $stockNegative['available_stock'], 'Negative stock must be clamped to zero.');
        $this->assertSame(5, $stockNegative['reorder_quantity'], 'Reorder must use clamped zero stock.');

        // Assert: null lead time → insufficient-data with no false reorder
        $this->assertNotNull($stockNullLead, 'Product with null lead time must appear in response.');
        $this->assertSame('insufficient-data', $stockNullLead['status']);
        $this->assertSame(0, $stockNullLead['reorder_quantity'], 'Invalid lead time must not produce a reorder quantity.');
        $this->assertFalse($stockNullLead['has_warning'] ?? true, 'Invalid lead time must not trigger a warning.');
        $this->assertNull($stockNullLead['lead_time_days']);
        $this->assertNull($stockNullLead['lead_time_demand']);

        // Assert: negative lead time → insufficient-data with no false reorder
        $this->assertNotNull($stockNegLead, 'Product with negative lead time must appear in response.');
        $this->assertSame('insufficient-data', $stockNegLead['status']);
        $this->assertSame(0, $stockNegLead['reorder_quantity'], 'Negative lead time must not produce a reorder quantity.');
        $this->assertFalse($stockNegLead['has_warning'] ?? true, 'Negative lead time must not trigger a warning.');
        $this->assertNull($stockNegLead['lead_time_days']);
        $this->assertNull($stockNegLead['lead_time_demand']);
    }

    // ------------------------------------------------------------------ //
    // Cycle 4 — stock-planning endpoint is admin-only                     //
    // ------------------------------------------------------------------ //

    public function test_stock_planning_endpoint_is_admin_only(): void
    {
        // Arrange: create an outlet user for non-admin requests
        $outletUser = User::factory()->outlet()->create([
            'email' => 'stock-plan-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletToken = $this->postJson('/api/auth/login', [
            'email' => $outletUser->email,
            'password' => 'password123',
        ])->json('data.token');

        // -- Unauthenticated → HTTP 401 --
        $unauthResponse = $this->getJson('/api/admin/analytics/stock-planning');
        $unauthResponse->assertUnauthorized();
        // Must not expose any stock fields
        $unauthBody = $unauthResponse->json();
        $this->assertArrayNotHasKey('data', $unauthBody);
        $this->assertArrayNotHasKey('items', $unauthBody);

        // -- Outlet user (non-admin) → HTTP 403 with existing JSON auth contract --
        $forbiddenResponse = $this->withHeaders(['Authorization' => "Bearer {$outletToken}"])
            ->getJson('/api/admin/analytics/stock-planning');
        $forbiddenResponse->assertForbidden();
        $forbiddenBody = $forbiddenResponse->json();
        $this->assertSame('error', $forbiddenBody['status'] ?? null);
        $this->assertArrayHasKey('message', $forbiddenBody);
        // Must not expose stock planning data
        $this->assertArrayNotHasKey('data', $forbiddenBody);
        $this->assertArrayNotHasKey('items', $forbiddenBody);

        // -- Admin → HTTP 200 with stock-planning data --
        $adminResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/admin/analytics/stock-planning');
        $adminResponse->assertOk()->assertJsonPath('status', 'success');
        $this->assertArrayHasKey('items', $adminResponse->json('data', []));
    }
}
