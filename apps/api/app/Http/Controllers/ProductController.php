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

        $products = Product::search($search)->get();

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }
}
