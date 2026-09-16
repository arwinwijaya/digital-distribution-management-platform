<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPriceService
{
    /**
     * Update product price atomically with history logging.
     *
     * Price must be >= 0 (0 allowed for free items).
     */
    public function updatePrice(int $productId, float $price, User $actor): Product
    {
        return DB::transaction(function () use ($productId, $price, $actor): Product {
            $product = Product::lockForUpdate()->findOrFail($productId);
            $oldPrice = (float) $product->price;

            // No-op: same price, no history row needed
            if (abs($oldPrice - $price) < 0.005) {
                return $product;
            }

            ProductPriceHistory::create([
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $price,
                'changed_by' => $actor->id,
            ]);

            $product->price = $price;
            $product->save();

            return $product->fresh();
        });
    }
}
