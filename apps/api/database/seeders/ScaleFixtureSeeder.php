<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reproducible production-scale fixture for local profiling and load tests.
 * It intentionally generates data rather than committing a large SQL dump.
 */
class ScaleFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (Supplier::query()->where('name', 'Scale Fixture Supplier 1')->exists()) {
            $this->command?->info('Scale fixture already exists; no rows were added.');

            return;
        }

        $suppliers = collect(range(1, 5))->map(function (int $number) {
            return Supplier::factory()->create([
                'name' => 'Scale Fixture Supplier '.$number,
                'subscription_status' => $number <= 4 ? 'active' : 'inactive',
                'subscription_plan' => $number === 1 ? 'premium' : 'basic',
            ]);
        });

        // Keep exactly 100 active outlet identities while representing 500 outlets.
        $activeOutlets = collect(range(1, 100))->map(function () {
            $user = User::factory()->outlet()->create();

            return Outlet::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        });
        Outlet::factory()->count(400)->create(['is_active' => false]);

        $products = collect();
        foreach ($suppliers as $supplier) {
            $products = $products->merge(Product::factory()->count(40)->create([
                'supplier_id' => $supplier->id,
                'is_active' => $supplier->subscription_status === 'active',
                'stock_quantity' => 100,
            ]));
        }
        // Supplier-less legacy products keep the catalog representative of mixed data.
        $products = $products->merge(Product::factory()->count(20)->create(['supplier_id' => null]));

        // A modest order history is enough to exercise dashboard aggregates without
        // turning the repository into a fixture dump.
        DB::transaction(function () use ($activeOutlets, $products): void {
            foreach (range(1, 500) as $number) {
                $product = $products->random();
                $quantity = random_int(1, 5);
                $order = Order::create([
                    'order_id' => sprintf('SCALE-%06d', $number),
                    'outlet_id' => $activeOutlets->random()->id,
                    'status' => $number % 20 === 0 ? 'Delivered' : 'New',
                    'total_amount' => (float) $product->price * $quantity,
                    'paid_amount' => 0,
                    'commission_percentage' => 2,
                    'idempotency_key' => 'scale-fixture-'.$number,
                ]);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => (float) $product->price * $quantity,
                ]);
            }
        });

        $this->command?->info('Scale fixture generated: 500 outlets (100 active), 5 suppliers, 220 products, 500 orders.');
    }
}
