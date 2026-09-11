<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use App\Services\FinanceAuthorizationService;
use App\Services\InvoiceMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FinanceMetricsController extends Controller
{
    public function __construct(
        private readonly InvoiceMetricsService $metrics,
        private readonly FinanceAuthorizationService $authorization,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user && $this->authorization->isAdmin($user);
        $isFinance = $user && $this->authorization->isFinance($user);
        $isOutlet = $user && $this->authorization->hasCurrentRole($user, 'outlet');
        $outlet = $isOutlet ? $user->outlet : null;

        if (! $isAdmin && ! $isFinance && (! $isOutlet || ! $outlet || ! $outlet->is_active)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validator = Validator::make($request->query(), [
            'start_date' => ['sometimes', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'outlet_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $timezone = 'Asia/Jakarta';
        $asOf = Carbon::now($timezone);
        $end = Carbon::createFromFormat('Y-m-d', $request->query('end_date', $asOf->toDateString()), $timezone)->endOfDay();
        $start = Carbon::createFromFormat('Y-m-d', $request->query('start_date', $end->toDateString()), $timezone)->startOfDay();
        if (! $request->query('start_date')) {
            $start = $end->copy()->subDays(29)->startOfDay();
        }

        $rangeDays = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        if ($rangeDays > AnalyticsService::MAX_DATE_RANGE_DAYS) {
            $message = sprintf('The date range cannot exceed %d days.', AnalyticsService::MAX_DATE_RANGE_DAYS);
            return response()->json([
                'message' => $message,
                'errors' => ['date_range' => [$message]],
            ], 422);
        }

        $outletId = null;
        if ($isAdmin || $isFinance) {
            $outletId = $request->query('outlet_id') !== null ? (int) $request->query('outlet_id') : null;
        } else {
            $outletId = $outlet->id;
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->metrics->metrics($start, $end, $asOf, $outletId),
        ]);
    }
}
