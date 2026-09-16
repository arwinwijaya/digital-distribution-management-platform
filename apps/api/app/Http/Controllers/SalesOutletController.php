<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesOutletController extends Controller
{
    /**
     * GET /sales/outlets — list outlets scoped to the authenticated sales user's territory.
     *
     * Only sales users with a non-null territory_id may call this endpoint.
     * Response is the territory-scoped active outlet list, ordered by name.
     * No unrestricted outlet access — query is always filtered by
     * Outlet::active()->inTerritory($user->territory_id).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSales()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Only sales users can list outlets.',
            ], 403);
        }

        if ($user->territory_id === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Sales user must be assigned to a territory.',
            ], 403);
        }

        $outlets = Outlet::active()
            ->inTerritory((int) $user->territory_id)
            ->select('id', 'name', 'category', 'city', 'district', 'territory_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $outlets,
        ]);
    }
}
