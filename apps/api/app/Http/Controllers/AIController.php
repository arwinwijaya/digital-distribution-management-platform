<?php

namespace App\Http\Controllers;

use App\Models\RecommendationEvent;
use App\Services\ForecastService;
use App\Services\Recommendation\RecommendationModelAdapter;
use App\Services\RecommendationService;
use App\Services\SegmentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    public function __construct(
        private readonly RecommendationModelAdapter $recommendationAdapter,
        private readonly ForecastService $forecastService,
        private readonly SegmentationService $segmentationService,
        private readonly RecommendationService $recommendationService,
    ) {}

    public function recommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['sometimes', 'integer', 'exists:outlets,id'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);
        $outletId = $this->resolveOutletId($request, $validated['outlet_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'data' => $this->recommendationAdapter->resolve()->predict($outletId, (int) ($validated['limit'] ?? RecommendationService::DEFAULT_LIMIT)),
        ]);
    }

    public function discovery(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isOutlet()) {
            return $this->forbidden('Only outlet users can access product discovery.');
        }

        $validated = $request->validate([
            'outlet_id' => ['sometimes', 'integer', 'exists:outlets,id'],
            'limit' => ['sometimes', 'integer'],
        ]);
        $limit = (int) ($validated['limit'] ?? RecommendationService::DISCOVERY_DEFAULT_LIMIT);
        $requestedOutletId = isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null;

        try {
            $outletId = $this->resolveOutletId($request, $requestedOutletId);
        } catch (HttpException $exception) {
            return $this->forbidden($exception->getMessage());
        }

        try {
            $data = $this->recommendationService->discover($outletId, $limit);
        } catch (\Throwable $exception) {
            Log::error('Failed to fetch outlet product discovery.', [
                'user_id' => $user->id,
                'outlet_id' => $outletId,
                'exception' => $exception,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch product discovery recommendations.',
            ], 500);
        }

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function storeDiscoveryEvent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isOutlet()) {
            return $this->forbidden('Only outlet users can record product discovery events.');
        }

        $outlet = $user->outlet;
        if (! $outlet) {
            return $this->forbidden('The authenticated user is not associated with an outlet.');
        }

        $payload = $request->validate([
            'event_uuid' => ['required', 'string', 'max:128'],
            'event_type' => ['required', 'string', 'in:displayed,clicked,cart'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'occurred_at' => ['required', 'date'],
        ]);

        $outletId = (int) $outlet->id;
        $occurredAt = \Carbon\Carbon::parse($payload['occurred_at'])->format('Y-m-d H:i:s');
        $existing = RecommendationEvent::where('event_uuid', $payload['event_uuid'])->first();

        if ($existing !== null) {
            if (
                $existing->event_type === $payload['event_type']
                && (int) $existing->outlet_id === $outletId
                && (int) $existing->product_id === (int) $payload['product_id']
                && $existing->occurred_at->format('Y-m-d H:i:s') === $occurredAt
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
                'outlet_id' => $outletId,
                'product_id' => (int) $payload['product_id'],
                'occurred_at' => $payload['occurred_at'],
                'metadata' => ['source' => 'discovery_v1'],
            ]);
        } catch (\Throwable $exception) {
            // A concurrent retry can race between the lookup and unique insert.
            $existing = RecommendationEvent::where('event_uuid', $payload['event_uuid'])->first();
            if ($existing !== null) {
                if (
                    $existing->event_type === $payload['event_type']
                    && (int) $existing->outlet_id === $outletId
                    && (int) $existing->product_id === (int) $payload['product_id']
                    && $existing->occurred_at->format('Y-m-d H:i:s') === $occurredAt
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

            throw $exception;
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

    public function forecast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['sometimes', 'integer', 'exists:outlets,id'],
            'period' => ['required', 'string', 'in:daily,weekly,monthly'],
            'horizon' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        $outletId = $this->resolveOutletId($request, $validated['outlet_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'data' => $this->forecastService->forecast($outletId, $validated['period'], (int) $validated['horizon']),
        ]);
    }

    public function segmentation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['sometimes', 'integer', 'exists:outlets,id'],
        ]);
        $user = $request->user();
        $requestedOutletId = isset($validated['outlet_id']) ? (int) $validated['outlet_id'] : null;

        if (! $user->isAdmin() && ! $user->isOutlet()) {
            return $this->forbidden('Only admins and outlet users can view segmentation.');
        }

        if ($user->isOutlet()) {
            $outlet = $user->outlet;
            if (! $outlet) {
                return $this->forbidden('The authenticated user is not associated with an outlet.');
            }
            if ($requestedOutletId !== null && $requestedOutletId !== (int) $outlet->id) {
                return $this->forbidden('An outlet can only view its own AI signals.');
            }
            $requestedOutletId = (int) $outlet->id;
        }

        $data = $requestedOutletId === null
            ? ['segments' => $this->segmentationService->segmentAll(), 'limit' => 100]
            : $this->segmentationService->segment($requestedOutletId);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    private function resolveOutletId(Request $request, ?int $requestedOutletId): ?int
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return $requestedOutletId;
        }
        if (! $user->isOutlet()) {
            abort(403, 'Only admins and outlet users can access AI analytics.');
        }

        $outlet = $user->outlet;
        if (! $outlet) {
            abort(403, 'The authenticated user is not associated with an outlet.');
        }
        if ($requestedOutletId !== null && $requestedOutletId !== (int) $outlet->id) {
            abort(403, 'An outlet can only view its own AI signals.');
        }

        return (int) $outlet->id;
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], 403);
    }
}
