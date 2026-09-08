<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppController extends Controller
{
    public function __construct(private readonly WhatsAppService $whatsappService) {}

    public function webhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['status' => 'error', 'message' => 'Malformed JSON webhook payload.'], 400);
        }

        $result = $this->whatsappService->handleWebhook($payload, $rawBody, [
            'x-hub-signature-256' => $request->header('X-Hub-Signature-256'),
            'x-whatsapp-signature' => $request->header('X-WhatsApp-Signature'),
            'x-whatsapp-token' => $request->header('X-WhatsApp-Token') ?? $request->header('X-WhatsApp-Verify-Token'),
        ]);
        $status = (int) ($result['http_status'] ?? 200);
        unset($result['http_status']);

        if (($result['data'] ?? null) instanceof Order) {
            $result['data'] = $this->formatOrder($result['data']);
        }

        return response()->json($result, $status);
    }

    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }
        $outlet = $user->outlet;
        if ($user->isAdmin()) {
            $phone = $request->string('phone')->toString();
            $outlet = Outlet::where('phone', $phone)->where('is_active', true)->first();
        }
        if (! $outlet) {
            return response()->json(['status' => 'error', 'message' => 'A verified active outlet is required.'], 422);
        }

        $result = $this->whatsappService->shareCatalog($outlet);

        return response()->json([
            'status' => $result['status'],
            'data' => [
                'message_id' => $result['message']->id,
                'catalog' => $result['message']->payload['catalog'] ?? [],
            ],
        ], $result['status'] === 'failed' ? 503 : ($result['status'] === 'disabled' ? 202 : 200));
    }

    public function notify(Request $request, int $orderId): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Only admins can send order notifications.'], 403);
        }
        $order = Order::with('outlet')->findOrFail($orderId);
        $message = $this->whatsappService->notifyConfirmedOrder($order);
        if (! $message) {
            return response()->json(['status' => 'disabled', 'message' => 'WhatsApp integration is unavailable.'], 202);
        }

        return response()->json(['status' => $message->status, 'data' => $message], $message->status === 'failed' ? 503 : 200);
    }

    public function retry(Request $request, int $messageId): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['status' => 'error', 'message' => 'Only admins can retry provider messages.'], 403);
        }
        $message = WhatsAppMessage::where('direction', 'outbound')->findOrFail($messageId);
        $message = $this->whatsappService->retryMessage($message);

        return response()->json(['status' => $message->status, 'data' => $message], $message->status === 'failed' ? 503 : 200);
    }

    /** @return array<string, mixed> */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'outlet_id' => $order->outlet_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->values(),
        ];
    }
}
