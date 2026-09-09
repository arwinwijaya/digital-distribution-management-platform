<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List products with optional search filter
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        // Legacy catalog stays backward-compatible for supplier-less products,
        // but must not expose products owned by inactive suppliers.
        $products = Product::search($search)
            ->where(function ($query) {
                $query->whereNull('supplier_id')
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('subscription_status', 'active'));
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }
}
