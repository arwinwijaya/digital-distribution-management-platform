<?php

namespace App\Http\Controllers;

use App\Services\ActiveDataSnapshotReader;
use App\Services\GeographicAnalyticsService;
use Carbon\Carbon;
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

        if ($version === null) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'snapshot_available' => false,
                    'geographic_section_available' => false,
                    'snapshot_version' => null,
                    'window' => null,
                    'table' => [],
                    'map_points' => [],
                    'meta' => [
                        'truncated' => false,
                        'omitted_zero_days' => 0,
                        'product_summary_capped' => false,
                    ],
                ],
            ]);
        }

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
                // Explicit v2 field projection — every v2 field is listed by
                // name so a future field addition fails loudly instead of
                // being silently dropped. Invalid-coordinate rows are retained
                // with plottable=false, never removed.
                $mapPoints[] = [
                    'outlet_id' => $payload['outlet_id'] ?? null,
                    'outlet_name' => $payload['outlet_name'] ?? null,
                    'territory' => $payload['territory'] ?? null,
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'plottable' => $payload['plottable']
                        ?? GeographicAnalyticsService::isValidCoordinate(
                            $payload['latitude'] ?? null,
                            $payload['longitude'] ?? null
                        ),
                    'orders' => $payload['orders'] ?? 0,
                    'sales' => $payload['sales'] ?? '0.00',
                    'orders_by_status' => $payload['orders_by_status'] ?? null,
                    'sales_by_status' => $payload['sales_by_status'] ?? null,
                    'daily_by_status' => $payload['daily_by_status'] ?? null,
                    'product_summary' => $payload['product_summary'] ?? null,
                    'product_summary_truncated' => $payload['product_summary_truncated'] ?? null,
                    'latest_request' => $payload['latest_request'] ?? null,
                ];
                continue;
            }
        }

        $geographicSectionAvailable = $table !== [] || $mapPoints !== [];
        $windowDays = $this->windowDays($window);

        $omittedZeroDays = 0;
        $productSummaryCapped = false;
        foreach ($mapPoints as $point) {
            if (($point['product_summary_truncated'] ?? null) === true) {
                $productSummaryCapped = true;
            }
            // Rows with zero orders or without a daily_by_status array
            // contribute nothing — omitted days are intentional compression.
            if (($point['orders'] ?? 0) > 0 && is_array($point['daily_by_status'] ?? null)) {
                $omittedZeroDays += max(0, $windowDays - count($point['daily_by_status']));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'snapshot_available' => true,
                'geographic_section_available' => $geographicSectionAvailable,
                'snapshot_version' => $version,
                'window' => $window,
                'table' => $table,
                'map_points' => $mapPoints,
                'meta' => [
                    'truncated' => false,
                    'omitted_zero_days' => $omittedZeroDays,
                    'product_summary_capped' => $productSummaryCapped,
                ],
            ],
        ]);
    }

    private function windowDays(?array $window): int
    {
        if (!is_array($window) || !isset($window['start'], $window['end'])) {
            return 0;
        }

        try {
            $start = Carbon::parse($window['start']);
            $end = Carbon::parse($window['end']);

            return $start->diffInDays($end) + 1;
        } catch (\Throwable) {
            return 0;
        }
    }
}
