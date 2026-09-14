<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Territory;
use App\Services\GeographicAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view territories.',
            ], 403);
        }

        $territories = Territory::all();

        return response()->json([
            'status' => 'success',
            'data' => $territories,
        ]);
    }

    public function store(\App\Http\Requests\StoreTerritoryRequest $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can manage territories.',
            ], 403);
        }

        $territory = Territory::create([
            'name' => $request->validated('name'),
            'code' => \Illuminate\Support\Str::slug($request->validated('name')),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $territory,
        ], 201);
    }

    public function update(\App\Http\Requests\UpdateTerritoryRequest $request, int $territoryId): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can manage territories.',
            ], 403);
        }

        $territory = Territory::findOrFail($territoryId);
        $territory->update([
            'name' => $request->validated('name'),
            'code' => \Illuminate\Support\Str::slug($request->validated('name')),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $territory->fresh(),
        ]);
    }

    public function assign(\App\Http\Requests\AssignTerritoryRequest $request, int $territoryId): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can manage territories.',
            ], 403);
        }

        $territory = Territory::findOrFail($territoryId);
        $outlet = Outlet::findOrFail($request->validated('outlet_id'));
        $outlet->update(['territory_id' => $territory->id]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'territory' => $territory->fresh(),
                'outlet' => $outlet->fresh(),
            ],
        ]);
    }
}
