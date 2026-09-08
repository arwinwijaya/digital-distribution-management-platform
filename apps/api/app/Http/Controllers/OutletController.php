<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOutletRequest;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;

class OutletController extends Controller
{
    /**
     * Register a new outlet
     */
    public function store(StoreOutletRequest $request): JsonResponse
    {
        $outlet = Outlet::create(array_merge($request->validated(), [
            'is_active' => true,
        ]));

        return response()->json([
            'status' => 'success',
            'data' => $outlet,
        ], 201);
    }
}
