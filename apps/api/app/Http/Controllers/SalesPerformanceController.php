<?php

namespace App\Http\Controllers;

use App\Models\SalesTarget;
use App\Models\User;
use App\Services\FinanceAuthorizationService;
use App\Services\SalesPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesPerformanceController extends Controller
{
    public function __construct(
        private readonly SalesPerformanceService $performanceService,
        private readonly FinanceAuthorizationService $authz,
    ) {
    }

    /**
     * GET /sales/my-performance — sales user's own performance for the current
     * (or requested) period.
     *
     * Requires sales role; admins must use adminPerformance.
     */
    public function myPerformance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSales()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Only sales users can view personal performance.',
            ], 403);
        }

        $period = $this->resolvePeriod($request);

        $perf = $this->performanceService->calculatePerformance((int) $user->id, $period);

        return response()->json([
            'status' => 'success',
            'data'   => array_merge([
                'user_id' => $user->id,
                'name'    => $user->name,
                'period'  => $period,
            ], $this->formatAmounts($perf)),
        ]);
    }

    /**
     * GET /admin/sales/performance?period=YYYY-MM — all sales users' performance.
     *
     * Admin-only (sales users receive 403).
     * Returns paginated limit+1 over sales users.
     */
    public function adminPerformance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->authz->isAdminOrOwner($user)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Only admins can view all sales performance.',
            ], 403);
        }

        $period = $this->resolvePeriod($request);

        $limit  = min(max((int) $request->integer('limit', 100), 1), 100);
        $cursor = $request->integer('cursor');

        $query = User::where('role', 'sales')->orderBy('id');

        if ($cursor > 0) {
            $query->where('id', '>', $cursor);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values()->map(function (User $salesUser) use ($period) {
            $perf = $this->performanceService->calculatePerformance((int) $salesUser->id, $period);

            return array_merge([
                'user_id' => $salesUser->id,
                'name'    => $salesUser->name,
                'email'   => $salesUser->email,
                'period'  => $period,
            ], $this->formatAmounts($perf));
        });

        return response()->json([
            'status'      => 'success',
            'data'        => $data,
            'has_more'    => $hasMore,
            'next_cursor' => $hasMore ? $data->last()['user_id'] ?? null : null,
        ]);
    }

    /**
     * Format monetary fields as fixed 2-decimal strings, matching the
     * `decimal:2` cast convention used by other sales/monetary endpoints.
     *
     * @param  array{target: float, achievement: float, percentage: float, order_count: int}  $perf
     * @return array{target: string, achievement: string, percentage: string, order_count: int}
     */
    private function formatAmounts(array $perf): array
    {
        return array_merge($perf, [
            'target'      => number_format((float) $perf['target'], 2, '.', ''),
            'achievement' => number_format((float) $perf['achievement'], 2, '.', ''),
            'percentage'  => number_format((float) $perf['percentage'], 2, '.', ''),
        ]);
    }

    /**
     * Resolve the period from the request, defaulting to the current month.
     *
     * @return string YYYY-MM
     */
    private function resolvePeriod(Request $request): string
    {
        $period = $request->string('period', now()->format('Y-m'))->toString();
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            $period = now()->format('Y-m');
        }

        return $period;
    }
}
