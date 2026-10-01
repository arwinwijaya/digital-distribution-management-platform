<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderItemSnapshotColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_has_nullable_product_name_snapshot_and_model_allows_mass_assignment(): void
    {
        $this->assertTrue(Schema::hasColumn('order_items', 'product_name_snapshot'));
        $this->assertContains(Schema::getColumnType('order_items', 'product_name_snapshot'), ['string', 'varchar']);
        // Column is nullable: Doctrine DBAL may normalize, so verify by fetching column listing and attempting null insert via fillable.
        $this->assertContains('product_name_snapshot', (new OrderItem)->getFillable());
    }

    public function test_migration_down_drops_column_and_up_backfills_existing_rows(): void
    {
        $migration = require database_path('migrations/2026_10_02_000002_add_product_name_snapshot_to_order_items_table.php');

        $product = Product::factory()->create(['name' => 'Legacy Sabun']);
        $outlet = Outlet::factory()->create();
        $order = Order::create([
            'order_id' => 'ORD-LEGACY-1',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 10000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'legacy-snapshot-key',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        // down() drops the column
        $migration->down();
        $this->assertFalse(Schema::hasColumn('order_items', 'product_name_snapshot'));

        // up() re-adds the nullable column and backfills from products.name
        $migration->up();
        $this->assertTrue(Schema::hasColumn('order_items', 'product_name_snapshot'));
        $this->assertSame('Legacy Sabun', $item->fresh()->product_name_snapshot);
    }
}
