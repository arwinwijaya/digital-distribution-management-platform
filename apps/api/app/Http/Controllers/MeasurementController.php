<?php

namespace App\Http\Controllers;

use App\Models\RecommendationEvent;
use App\Services\MeasurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class MeasurementController extends Controller
{
    public function __construct(private readonly MeasurementService $measurementService)
    {
    }

    public function recommendations(Request $request): JsonResponse
    {
        if ($error = $this->ensureAdmin($request)) {
            return $error;
        }
        $version = $this->version();
        $window = $this->window();
        $rows = $this->snapshotRows();

        return response()->json([
            'status' => 'success',
            'data' => $this->measurementService->recommendationMeasurementFromSnapshot($rows, $version, $window),
        ]);
    }

    public function forecasts(Request $request): JsonResponse
    {
        if ($error = $this->ensureAdmin($request)) {
            return $error;
        }
        $version = $this->version();
        $window = $this->window();
        $rows = $this->snapshotRows();
        $fixture = $request->input('fixture');

        return response()->json([
            'status' => 'success',
            'data' => $this->measurementService->forecastMeasurementFromSnapshot($rows, $version, $window, $fixture),
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        if ($error = $this->ensureAdmin($request)) {
            return $error;
        }

        $payload = $request->validate([
            'event_uuid' => ['required', 'string', 'max:128'],
            'event_type' => ['required', 'string', 'in:displayed,clicked,cart'],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'occurred_at' => ['required', 'date'],
        ]);

        $existing = RecommendationEvent::where('event_uuid', $payload['event_uuid'])->first();

        if ($existing !== null) {
            if (
                $existing->event_type === $payload['event_type']
                && (int) $existing->outlet_id === (int) $payload['outlet_id']
                && (int) $existing->product_id === (int) $payload['product_id']
                && $existing->occurred_at->format('Y-m-d H:i:s') === \Carbon\Carbon::parse($payload['occurred_at'])->format('Y-m-d H:i:s')
            ) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Event already recorded.',
                    'data' => [
                        'id' => (int) $existing->id,
                        'event_uuid' => $existing->event_uuid,
                        'idempotent_replay' => true,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Event UUID is already used with conflicting attributes.',
            ], 409);
        }

        try {
            $event = RecommendationEvent::create([
                'event_uuid' => $payload['event_uuid'],
                'event_type' => $payload['event_type'],
                'outlet_id' => $payload['outlet_id'],
                'product_id' => $payload['product_id'],
                'occurred_at' => $payload['occurred_at'],
                'metadata' => $request->input('metadata'),
            ]);
        } catch (Throwable $e) {
            // Catch any other unique/transactional errors
            return response()->json([
                'status' => 'error',
                'message' => 'Event could not be recorded.',
                'error' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event recorded.',
            'data' => [
                'id' => (int) $event->id,
                'event_uuid' => $event->event_uuid,
            ],
        ], 201);
    }

    private function version(): ?int
    {
        $reader = app(\App\Services\ActiveDataSnapshotReader::class);

        return $reader->version();
    }

    private function window(): ?array
    {
        $reader = app(\App\Services\ActiveDataSnapshotReader::class);

        return $reader->window();
    }

    private function snapshotRows(): array
    {
        $reader = app(\App\Services\ActiveDataSnapshotReader::class);

        return $reader->section('measurement');
    }

    private function ensureAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }
        if (! $request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can access measurement.',
            ], 403);
        }

        return null;
    }
}
