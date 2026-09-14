<?php

namespace App\Http\Controllers;

use App\Services\ActiveDataSnapshotReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockPlanningController extends Controller
{
    public function __construct(private readonly ActiveDataSnapshotReader $snapshotReader)
    {
    }

    /**
     * Admin-only consumer boundary.
     * Authorization lives exclusively at this HTTP layer; the service formula
     * stays deterministic and unaware of roles.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view stock planning analytics.',
            ], 403);
        }

        $version = $this->snapshotReader->version();
        $window = $this->snapshotReader->window();
        $rows = $this->snapshotReader->section('stock');

        $items = [];

        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                continue;
            }

            $items[] = $payload;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'items' => $items,
                'snapshot_version' => $version,
                'window' => $window,
            ],
        ]);
    }
}
