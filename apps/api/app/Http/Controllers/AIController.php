<?php

namespace App\Http\Controllers;

use App\Services\ForecastService;
use App\Services\RecommendationService;
use App\Services\SegmentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(
        private readonly RecommendationService $recommendationService,
        private readonly ForecastService $forecastService,
        private readonly SegmentationService $segmentationService,
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
            'data' => $this->recommendationService->recommend($outletId, (int) ($validated['limit'] ?? RecommendationService::DEFAULT_LIMIT)),
        ]);
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
