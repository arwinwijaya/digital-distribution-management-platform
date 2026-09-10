<?php

namespace App\Http\Controllers;

use App\Http\Requests\SetPaymentTermRequest;
use App\Http\Requests\StoreOutletRequest;
use App\Models\Outlet;
use App\Services\FinanceAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function __construct(private readonly FinanceAuthorizationService $authorization)
    {
    }

    /**
     * Register a new outlet
     */
    public function store(StoreOutletRequest $request): JsonResponse
    {
        $outlet = Outlet::create(array_merge($request->validated(), [
            'is_active' => true,
        ]));

        return response()->json([
            'status' => 'success',
            'data' => $outlet,
        ], 201);
    }

    public function showPaymentTerms(Request $request, int $outletId): JsonResponse
    {
        $this->authorization->assertAdmin($request->user());
        $outlet = Outlet::findOrFail($outletId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'outlet_id' => $outlet->id,
                'payment_term_days' => $outlet->effectivePaymentTermDays(),
            ],
        ]);
    }

    public function updatePaymentTerms(SetPaymentTermRequest $request, int $outletId): JsonResponse
    {
        $outlet = Outlet::findOrFail($outletId);
        $outlet->update([
            'payment_term_days' => $request->validated('payment_term_days'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'outlet_id' => $outlet->id,
                'payment_term_days' => $outlet->fresh()->effectivePaymentTermDays(),
            ],
        ]);
    }
}
