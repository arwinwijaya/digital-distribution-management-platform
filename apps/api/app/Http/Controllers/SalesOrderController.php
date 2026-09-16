<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesOrderRequest;
use App\Models\Outlet;
use App\Services\OrderCreationService;
use Illuminate\Http\JsonResponse;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
    ) {
    }

    /**
     * POST /sales/orders — sales user creates an order on behalf of an outlet.
     *
     * Territory binding: the sales user's territory_id must match the outlet's
     * territory_id. A null territory_id on the sales user is a 403.
     *
     * Delegates to OrderCreationService::create (no parallel creation path).
     */
    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $outlet = Outlet::findOrFail($validated['outlet_id']);

        // Territory binding check.
        if ($user->territory_id === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. You must be assigned to a territory to place orders.',
            ], 403);
        }

        if ((int) $outlet->territory_id !== (int) $user->territory_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. You cannot place orders for outlets outside your territory.',
            ], 403);
        }

        $requestIdentity = $this->requestIdentity($outlet->id, $validated['idempotency_key']);

        $result = $this->orderCreationService->create(
            $validated,
            $outlet,
            $requestIdentity,
            (int) $user->id,
        );

        $result['order']->load('items.product');

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                 => $result['order']->id,
                'order_id'           => $result['order']->order_id,
                'outlet_id'          => $result['order']->outlet_id,
                'sales_user_id'      => $result['order']->sales_user_id,
                'status'             => $result['order']->status,
                'total_amount'       => $result['order']->total_amount,
                'paid_amount'        => $result['order']->paid_amount,
                'commission_percentage' => $result['order']->commission_percentage,
                'promotion_id'       => $result['order']->promotion_id,
                'discount_amount'    => $result['order']->discount_amount,
                'items' => $result['order']->items->map(fn ($item) => [
                    'id'           => $item->id,
                    'product_id'   => $item->product_id,
                    'product_name' => $item->product->name ?? null,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'subtotal'     => $item->subtotal,
                ]),
                'created_at' => $result['order']->created_at,
                'updated_at' => $result['order']->updated_at,
            ],
        ], $result['created'] ? 201 : 200);
    }

    private function requestIdentity(int $outletId, string $clientIdentity): string
    {
        return hash('sha256', json_encode([
            'outlet_id'        => $outletId,
            'request_identity' => $clientIdentity,
        ], JSON_THROW_ON_ERROR));
    }
}
