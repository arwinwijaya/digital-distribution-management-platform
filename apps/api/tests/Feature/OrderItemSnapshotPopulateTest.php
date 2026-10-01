<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemSnapshotPopulateTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_item_snapshot_is_populated_at_creation_and_survives_product_rename(): void
    {
        $product = Product::factory()->create([
            'name' => 'Sabun A',
            'price' => 10000,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $outlet = Outlet::factory()->create();
        $user = User::factory()->create();

        $service = app(OrderCreationService::class);
        $result = $service->create([
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ], $outlet, 'idempotency-key-snapshot-test-1', $user->id);

        $order = $result['order'];
        $order->load('items');

        $this->assertCount(1, $order->items);
        $item = $order->items->first();
        $this->assertEquals('Sabun A', $item->product_name_snapshot);

        // When product is renamed to Sabun B
        $product->update(['name' => 'Sabun B']);

        // Refresh item from DB
        $item->refresh();
        $this->assertEquals('Sabun A', $item->product_name_snapshot);
    }
}
