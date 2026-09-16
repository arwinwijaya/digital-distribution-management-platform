<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductPriceRequest;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Services\FinanceAuthorizationService;
use App\Services\ProductPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly FinanceAuthorizationService $authorization,
        private readonly ProductPriceService $priceService,
    ) {
    }

    /**
     * Admin price update (admin-only). Price >= 0, 0 allowed for free items.
     * Supplier users get 403.
     */
    public function update(UpdateProductPriceRequest $request, int $id): JsonResponse
    {
        $this->authorization->assertAdminOrOwner($request->user());

        $product = $this->priceService->updatePrice(
            $id,
            (float) $request->validated('price'),
            $request->user()
        );

        return response()->json([
            'status' => 'success',
            'data' => $this->format($product),
        ]);
    }

    /**
     * Price history for a product — cursor-limit pagination (limit+1).
     */
    public function prices(Request $request, int $id): JsonResponse
    {
        $this->authorization->assertAdminOrOwner($request->user());

        $product = Product::findOrFail($id);

        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $offset = max((int) $request->query('cursor', 0), 0);

        $rows = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'data' => $rows->map(fn (ProductPriceHistory $h) => $this->formatHistory($h))->all(),
                'meta' => [
                    'limit'     => $limit,
                    'cursor'    => $offset,
                    'has_more'  => $hasMore,
                    'next_cursor' => $hasMore ? $offset + $limit : null,
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Product $product): array
    {
        return [
            'id'    => $product->id,
            'name'  => $product->name,
            'price' => $product->price,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistory(ProductPriceHistory $history): array
    {
        return [
            'id'         => $history->id,
            'product_id' => $history->product_id,
            'old_price'  => $history->old_price,
            'new_price'  => $history->new_price,
            'changed_by' => $history->changed_by,
            'changed_at' => $history->changed_at?->toIso8601String(),
        ];
    }
}
