<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only admins can view analytics.',
            ], 403);
        }

        $validated = $request->validate([
            'group' => ['sometimes', 'string', 'in:daily,weekly,monthly'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $end = Carbon::parse($validated['end_date'] ?? now()->toDateString())->endOfDay();
        $start = Carbon::parse($validated['start_date'] ?? $end->copy()->subDays(29)->toDateString())->startOfDay();
        if ($start->greaterThan($end)) {
            return response()->json([
                'message' => 'The start date must be before or equal to the end date.',
                'errors' => ['start_date' => ['The start date must be before or equal to the end date.']],
            ], 422);
        }

        $rangeDays = $start->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        if ($rangeDays > AnalyticsService::MAX_DATE_RANGE_DAYS) {
            $message = sprintf('The date range cannot exceed %d days.', AnalyticsService::MAX_DATE_RANGE_DAYS);
            return response()->json([
                'message' => $message,
                'errors' => ['date_range' => [$message]],
            ], 422);
        }

        $data = $this->analyticsService->dashboard($start, $end, $validated['group'] ?? 'daily');

        // Keep the nested metrics contract while exposing descriptive aliases for
        // simple dashboard clients.
        $data += [
            'total_orders' => $data['metrics']['orders_total'],
            'total_sales' => $data['metrics']['sales_total'],
            'total_outlets' => $data['metrics']['outlets_total'],
            'total_products' => $data['metrics']['products_total'],
            'total_payments' => $data['metrics']['payments_total'],
            'total_outstanding' => $data['metrics']['outstanding_total'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
