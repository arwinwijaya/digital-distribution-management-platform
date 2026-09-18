<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesVisitRequest;
use App\Models\SalesVisit;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesController extends Controller
{
    public function __construct(private readonly CalendarService $calendar)
    {
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
}
