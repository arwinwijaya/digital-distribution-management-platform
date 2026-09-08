<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $result = $this->paymentService->record($request->validated(), $request->user());
        $payment = $result['payment']->load('order');

        return response()->json([
            'status' => 'success',
            'data' => $this->formatPayment($payment),
        ], $result['created'] ? 201 : 200);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payment::with('order')->latest();
        if (!$request->user()->isAdmin()) {
            $outlet = $request->user()->outlet;
            if (!$outlet) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }
            $query->where('outlet_id', $outlet->id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()->map(fn (Payment $payment) => $this->formatPayment($payment)),
        ]);
    }

    private function formatPayment(Payment $payment): array
    {
        $payment->loadMissing('order.items.product');
        $order = $payment->order;
        $paidCents = (int) round(((float) $order->paid_amount) * 100);
        $totalCents = (int) round(((float) $order->total_amount) * 100);

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'outlet_id' => $payment->outlet_id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
            'status' => $payment->status,
            'receipt_reference' => $payment->receipt_reference,
            'idempotency_key' => $payment->idempotency_key,
            'order' => [
                'id' => $order->id,
                'order_id' => $order->order_id,
                'total_amount' => $order->total_amount,
                'paid_amount' => $order->paid_amount,
                'outstanding_balance' => number_format(max(0, $totalCents - $paidCents) / 100, 2, '.', ''),
                'status' => $order->status,
            ],
            'receipt' => [
                'reference' => $payment->receipt_reference,
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'tax' => '0.00',
                'discount' => '0.00',
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? null,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ])->values(),
            ],
            'created_at' => $payment->created_at,
        ];
    }
}
