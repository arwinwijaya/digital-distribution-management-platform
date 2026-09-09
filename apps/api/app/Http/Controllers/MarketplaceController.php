<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    /**
     * Return only suppliers currently allowed to sell. The response is bounded
     * so an accidentally large supplier catalogue cannot become an unbounded
     * API response.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 50);

        $suppliers = Supplier::query()
            ->where('subscription_status', 'active')
            ->select(['id', 'name', 'subscription_plan'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => 'success',
            'data' => $suppliers->items(),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'per_page' => $suppliers->perPage(),
                'last_page' => $suppliers->lastPage(),
                'total' => $suppliers->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * List active products and their active supplier. Products owned by an
     * inactive supplier are intentionally filtered in SQL before pagination.
     * Legacy supplier-less products remain visible for existing catalog users.
     */
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:100'],
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $perPage = (int) ($validated['per_page'] ?? 25);

        $products = Product::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->where('products.is_active', true)
            ->where(function ($supplier) {
                $supplier->whereNull('products.supplier_id')
                    ->orWhere('suppliers.subscription_status', 'active');
            })
            ->when(isset($validated['supplier_id']), fn ($query) => $query->where('products.supplier_id', $validated['supplier_id']))
            ->when(isset($validated['search']), fn ($query) => $query->where('products.name', 'like', '%'.$validated['search'].'%'))
            ->select([
                'products.id', 'products.supplier_id', 'products.name',
                'products.description', 'products.price', 'products.sku',
                'products.stock_quantity', 'products.category', 'products.is_active',
                'suppliers.name as supplier_name',
            ])
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'status' => 'success',
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }
}
