<?php

namespace App\Http\Controllers;

use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Services\ActiveDataSnapshotReader;
use App\Services\DataPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin-only pipeline status and manual-trigger endpoints.
 */
class DataPipelineController extends Controller
{
    public function __construct(
        private readonly DataPipelineService $pipelineService,
        private readonly ActiveDataSnapshotReader $snapshotReader,
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view the pipeline status.',
            ], 403);
        }

        $run = DataPipelineRun::latest()->first();
        $snapshot = $this->snapshotReader->snapshot();
        $version = $this->snapshotReader->version();
        $window = $this->snapshotReader->window();

        $windowData = $window ?? [
            'start' => $run !== null ? $run->window_start->toDateString() : null,
            'end' => $run !== null ? $run->window_end->toDateString() : null,
            'timezone' => DataPipelineService::TIMEZONE,
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'run' => $run !== null ? [
                    'status' => $run->status,
                    'pipeline_version' => $run->pipeline_version,
                    'run_uuid' => $run->run_uuid,
                ] : null,
                'snapshot' => $snapshot !== null ? [
                    'version' => $version,
                    'snapshot_uuid' => $snapshot->snapshot_uuid,
                ] : null,
                'window' => $windowData,
            ],
        ]);
    }

    public function manualTrigger(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can trigger the pipeline.',
            ], 403);
        }

        if ($this->pipelineService->hasActiveRun()) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'A pipeline run is already active.',
            ], 409);
        }

        $run = $this->pipelineService->run();

        return response()->json([
            'status' => 'accepted',
            'data' => [
                'run_uuid' => $run->run_uuid,
                'run_status' => $run->status,
            ],
        ], 202);
    }
}
