<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetCreditLimitRequest;
use App\Models\CreditLimit;
use App\Models\Outlet;
use App\Services\CreditLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditLimitController extends Controller
{
    public function __construct(private readonly CreditLimitService $creditLimitService)
    {
    }

    public function show(Request $request, ?int $outletId = null): JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            $outlet = Outlet::findOrFail($outletId ?? abort(422, 'An outlet is required.'));
        } else {
            $outlet = $user->outlet;
            if (!$outlet || ($outletId !== null && $outlet->id !== $outletId)) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }
        }

        return response()->json(['status' => 'success', 'data' => $this->creditLimitService->summary($outlet)]);
    }

    public function update(SetCreditLimitRequest $request, int $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);
        $limit = CreditLimit::updateOrCreate(
            ['outlet_id' => $outlet->id],
            ['limit_amount' => $request->validated('limit_amount')],
        );

        return response()->json([
            'status' => 'success',
            'data' => array_merge(['outlet_id' => $outlet->id], $this->creditLimitService->summary($outlet)),
        ]);
    }
}
