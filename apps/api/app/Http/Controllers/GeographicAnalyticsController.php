<?php

namespace App\Http\Controllers;

use App\Services\ActiveDataSnapshotReader;
use App\Services\GeographicAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeographicAnalyticsController extends Controller
{
    public function __construct(private readonly ActiveDataSnapshotReader $snapshotReader)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view geographic analytics.',
            ], 403);
        }

        $version = $this->snapshotReader->version();
        $window = $this->snapshotReader->window();
        $rows = $this->snapshotReader->section('geographic');

        $table = [];
        $mapPoints = [];

        foreach ($rows as $row) {
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                continue;
            }

            $dimensionKey = (string) ($row['dimension_key'] ?? '');
            if (str_starts_with($dimensionKey, 'territory:')) {
                // Territory rows are pre-aggregated by the pipeline stage producer.
                $table[] = [
                    'territory' => $payload['territory'],
                    'sales' => $payload['sales'],
                    'orders' => $payload['orders'],
                    'outlets' => $payload['outlets'],
                ];
                continue;
            }

            if (str_starts_with($dimensionKey, 'outlet:')) {
                if (GeographicAnalyticsService::isValidCoordinate(
                    $payload['latitude'] ?? null,
                    $payload['longitude'] ?? null
                )) {
                    $mapPoints[] = [
                        'outlet_id' => $payload['outlet_id'],
                        'outlet_name' => $payload['outlet_name'],
                        'territory' => $payload['territory'],
                        'latitude' => $payload['latitude'],
                        'longitude' => $payload['longitude'],
                        'orders' => $payload['orders'],
                        'sales' => $payload['sales'],
                    ];
                }
                continue;
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'table' => $table,
                'map_points' => $mapPoints,
                'snapshot_version' => $version,
                'window' => $window,
            ],
        ]);
    }
}
