<?php

namespace App\Http\Controllers;

use App\Services\ActiveDataSnapshotReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPerformanceController extends Controller
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
                'message' => 'Unauthorized. Only admins can view supplier performance analytics.',
            ], 403);
        }

        $version = $this->snapshotReader->version();
        $window = $this->snapshotReader->window();
        $rows = $this->snapshotReader->section('supplier');

        $suppliers = [];

        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                continue;
            }

            $dimensionKey = (string) ($row['dimension_key'] ?? '');
            if (!str_starts_with($dimensionKey, 'supplier:')) {
                continue;
            }

            $suppliers[] = $payload;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'suppliers' => $suppliers,
                'snapshot_version' => $version,
                'window' => $window,
            ],
        ]);
    }
}
