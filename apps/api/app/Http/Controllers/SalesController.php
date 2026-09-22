<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesVisitRequest;
use App\Http\Requests\VisitCheckRequest;
use App\Models\SalesVisit;
use App\Services\CalendarService;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly GeoService $geo,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'sales'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Only sales users and admins can view visits.'], 403);
        }

        $query = SalesVisit::with(['salesUser:id,name,email', 'outlet']);
        if ($user->role === 'sales') {
            $query->where('sales_user_id', $user->id);
        }

        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 10);

        // Count the scoped rows BEFORE the offset/limit slice is applied.
        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'status' => 'success',
            'data' => $rows->take($limit)->values(),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }

    public function store(StoreSalesVisitRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $salesUserId = $user->isAdmin() ? ($validated['sales_user_id'] ?? $user->id) : $user->id;

        $salesUser = \App\Models\User::find($salesUserId);
        if (!$salesUser || !in_array($salesUser->role, ['sales', 'admin'], true)) {
            return response()->json(['status' => 'error', 'message' => 'The assigned user must be a sales user.'], 422);
        }

        $visit = DB::transaction(function () use ($validated, $salesUserId): SalesVisit {
            $visit = SalesVisit::create([
                ...$validated,
                'sales_user_id' => $salesUserId,
                'status' => $validated['status'] ?? 'planned',
            ]);
            $this->calendar->schedule($visit);
            return $visit;
        });

        return response()->json(['status' => 'success', 'data' => $visit->load(['salesUser:id,name,email', 'outlet'])], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $visit = SalesVisit::with(['salesUser:id,name,email', 'outlet'])->findOrFail($id);
        if ($request->user()->isAdmin() || ($request->user()->isSales() && $visit->sales_user_id === $request->user()->id)) {
            return response()->json(['status' => 'success', 'data' => $visit]);
        }

        return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $visit = SalesVisit::findOrFail($id);
        if (!$request->user()->isAdmin() && !($request->user()->isSales() && $visit->sales_user_id === $request->user()->id)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', 'in:planned,completed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visit_date' => ['sometimes', 'date_format:Y-m-d'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);
        $visit->update($validated);

        return response()->json(['status' => 'success', 'data' => $visit->fresh(['salesUser:id,name,email', 'outlet'])]);
    }

    /**
     * GPS check-in for a visit (Phase 8, T6).
     *
     * The visit must belong to the authenticated sales rep (admins may act on
     * any visit), must not already be checked in, and the supplied coordinates
     * must fall within `orders.visit_radius_m` of the outlet.
     */
    public function checkIn(VisitCheckRequest $request, int $id): JsonResponse
    {
        $visit = SalesVisit::with('outlet')->findOrFail($id);

        if (! $this->canActOnVisit($request, $visit)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        if ($visit->check_in_at !== null) {
            return response()->json(['status' => 'error', 'message' => 'Visit is already checked in.'], 409);
        }

        $radius = (float) config('orders.visit_radius_m', 200);
        if (! $this->withinOutletRadius($visit, (float) $request->float('latitude'), (float) $request->float('longitude'), $radius)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must be within '.$radius.' meters of the outlet to check in.',
            ], 422);
        }

        $visit->update([
            'check_in_at' => now(),
            'check_in_latitude' => $request->float('latitude'),
            'check_in_longitude' => $request->float('longitude'),
            'check_in_accuracy_m' => $request->integer('accuracy_m') ?: null,
            'notes' => $request->input('notes', $visit->notes),
        ]);

        return response()->json(['status' => 'success', 'data' => $visit->fresh(['salesUser:id,name,email', 'outlet'])]);
    }

    /**
     * GPS check-out for a visit; marks the visit `completed` (Phase 8, T6).
     */
    public function checkOut(VisitCheckRequest $request, int $id): JsonResponse
    {
        $visit = SalesVisit::with('outlet')->findOrFail($id);

        if (! $this->canActOnVisit($request, $visit)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        if ($visit->check_in_at === null) {
            return response()->json(['status' => 'error', 'message' => 'Visit must be checked in before checking out.'], 422);
        }

        $visit->update([
            'check_out_at' => now(),
            'check_out_latitude' => $request->float('latitude'),
            'check_out_longitude' => $request->float('longitude'),
            'status' => 'completed',
        ]);

        return response()->json(['status' => 'success', 'data' => $visit->fresh(['salesUser:id,name,email', 'outlet'])]);
    }

    /**
     * A sales rep may act only on their own visits; admins on any visit.
     */
    private function canActOnVisit(Request $request, SalesVisit $visit): bool
    {
        $user = $request->user();

        return $user->isAdmin() || ($user->isSales() && $visit->sales_user_id === $user->id);
    }

    /**
     * Radius guard; visits without an outlet or coordinates cannot be checked in.
     */
    private function withinOutletRadius(SalesVisit $visit, float $latitude, float $longitude, float $radius): bool
    {
        $outlet = $visit->outlet;
        if (! $outlet || $outlet->latitude === null || $outlet->longitude === null) {
            return false;
        }

        return $this->geo->withinRadius(
            $latitude,
            $longitude,
            (float) $outlet->latitude,
            (float) $outlet->longitude,
            $radius,
        );
    }
}
