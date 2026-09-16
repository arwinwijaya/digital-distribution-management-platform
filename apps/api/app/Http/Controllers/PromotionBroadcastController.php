<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Services\FinanceAuthorizationService;
use App\Services\WhatsAppOutboundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionBroadcastController extends Controller
{
    public function __construct(
        private readonly FinanceAuthorizationService $authz,
        private readonly WhatsAppOutboundService $outbound,
    ) {
    }

    public function broadcast(Request $request, int $id): JsonResponse
    {
        $this->authz->assertAdminOrOwner($request->user());

        $promo = Promotion::findOrFail($id);

        $result = $this->outbound->broadcastPromotion($promo);

        return response()->json([
            'status' => 'success',
            'data'   => $result,
            'already_sent' => $result['already_sent'] ?? false,
        ]);
    }
}
