<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ActiveDataSnapshotReader;
use App\Services\ReplenishmentService;
use App\Services\StockPlanningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReplenishmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(StockPlanningService $planning, ?ActiveDataSnapshotReader $reader = null): ReplenishmentService
    {
        $reader ??= Mockery::mock(ActiveDataSnapshotReader::class)->shouldReceive('section')->andReturn([])->getMock();

        return new ReplenishmentService($planning, $reader);
    }

    public function test_generate_creates_grouped_draft_without_mutating_stock_and_is_idempotent(): void
    {
        $actor = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['supplier_id' => $supplier->id, 'stock_quantity' => 3]);
        $window = ['start' => '2026-09-01', 'end' => '2026-09-30', 'timezone' => 'UTC'];
        $signals = ['product:'.$product->id => [
            'product_id' => $product->id, 'supplier_id' => $supplier->id,
            'reorder_quantity' => 7, 'status' => 'reorder',
        ]];
        $planning = Mockery::mock(StockPlanningService::class);
        $planning->shouldReceive('produce')->twice()->with($window)->andReturn($signals);

        $service = $this->service($planning);

        $first = $service->generate($window, $actor);
        $second = $service->generate($window, $actor);

        $this->assertCount(1, $first);
        $this->assertInstanceOf(ReplenishmentPlan::class, $first->first());
        $this->assertSame($first->first()->id, $second->first()->id);
        $this->assertSame(1, ReplenishmentPlan::count());
        $this->assertSame(1, ReplenishmentPlanItem::count());
        $this->assertSame('draft', $first->first()->status);
        $this->assertSame(7, (int) $first->first()->items->first()->reorder_quantity);
        $this->assertSame('sufficient', $first->first()->items->first()->data_sufficiency);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }

    public function test_generate_groups_signals_per_supplier(): void
    {
        $actor = User::factory()->create();
        $supplierA = Supplier::factory()->create();
        $supplierB = Supplier::factory()->create();
        $productA = Product::factory()->create(['supplier_id' => $supplierA->id]);
        $productB = Product::factory()->create(['supplier_id' => $supplierB->id]);
        $window = ['start' => '2026-09-01', 'end' => '2026-09-30'];
        $planning = Mockery::mock(StockPlanningService::class);
        $planning->shouldReceive('produce')->once()->with($window)->andReturn([
            'product:'.$productA->id => ['product_id' => $productA->id, 'supplier_id' => $supplierA->id, 'reorder_quantity' => 5, 'status' => 'reorder'],
            'product:'.$productB->id => ['product_id' => $productB->id, 'supplier_id' => $supplierB->id, 'reorder_quantity' => 9, 'status' => 'reorder'],
        ]);

        $plans = $this->service($planning)->generate($window, $actor);

        $this->assertCount(2, $plans);
        $this->assertSame(2, ReplenishmentPlan::count());
        $this->assertSame(2, ReplenishmentPlanItem::count());
    }

    public function test_generate_flags_supplierless_signal_as_insufficient(): void
    {
        $actor = User::factory()->create();
        $product = Product::factory()->create(['supplier_id' => null]);
        $otherProduct = Product::factory()->create(['supplier_id' => Supplier::factory()->create()->id]);
        $window = ['start' => '2026-09-01', 'end' => '2026-09-30'];
        $planning = Mockery::mock(StockPlanningService::class);
        $planning->shouldReceive('produce')->once()->andReturn([
            'product:'.$product->id => ['product_id' => $product->id, 'supplier_id' => null, 'reorder_quantity' => 4, 'status' => 'reorder'],
            'product:'.$otherProduct->id => ['product_id' => $otherProduct->id, 'supplier_id' => $otherProduct->supplier_id, 'reorder_quantity' => 0, 'status' => 'ok'],
        ]);

        $plans = $this->service($planning)->generate($window, $actor);

        $this->assertCount(1, $plans);
        $item = $plans->first()->items->first();
        $this->assertSame('insufficient', $item->data_sufficiency);
        $this->assertSame(0, (int) $item->reorder_quantity);
    }

    public function test_generate_with_no_reorder_signal_returns_empty_plan(): void
    {
        $actor = User::factory()->create();
        $planning = Mockery::mock(StockPlanningService::class);
        $planning->shouldReceive('produce')->once()->andReturn([]);

        $plans = $this->service($planning)->generate(['start' => '2026-09-01', 'end' => '2026-09-30'], $actor);

        $this->assertCount(0, $plans);
        $this->assertSame(0, ReplenishmentPlan::count());
    }

    public function test_generate_ignores_signals_without_reorder_and_with_supplier(): void
    {
        $actor = User::factory()->create();
        $window = ['start' => '2026-09-01', 'end' => '2026-09-30'];
        $planning = Mockery::mock(StockPlanningService::class);
        $planning->shouldReceive('produce')->once()->andReturn([
            'product:1' => ['product_id' => 1, 'supplier_id' => 4, 'reorder_quantity' => 0, 'status' => 'ok'],
        ]);

        $plans = $this->service($planning)->generate($window, $actor);

        $this->assertCount(0, $plans);
    }
}
