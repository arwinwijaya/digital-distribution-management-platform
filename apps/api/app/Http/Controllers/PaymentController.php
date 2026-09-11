<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Services\FinanceAuthorizationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly FinanceAuthorizationService $authorization,
    ) {
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
        $user = $request->user();
        $isAdmin = $this->authorization->isAdmin($user);
        $isFinance = $this->authorization->isFinance($user);
        $isOutlet = $this->authorization->hasCurrentRole($user, 'outlet');
        if (!$isAdmin && !$isFinance && !$isOutlet) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 25);
        $query = Payment::with('order')->orderByDesc('created_at')->orderByDesc('id');
        if (!$isAdmin && !$isFinance) {
            $outlet = $user->outlet;
            if (!$outlet) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
            }
            $query->where('outlet_id', $outlet->id);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rows->take($limit)->values()->map(fn (Payment $payment) => $this->formatPayment($payment)),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $rows->count() > $limit,
            ],
        ]);
    }

    private function formatPayment(Payment $payment): array
    {
        $payment->loadMissing('order.items.product');
        $order = $payment->order;
        $paidCents = $this->moneyToCents($order->paid_amount);
        $totalCents = $this->moneyToCents($order->total_amount);

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

    private function moneyToCents(mixed $amount): int
    {
        $normalized = trim((string) $amount);
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new \InvalidArgumentException('Money values must contain at most two decimal places.');
        }

        $wholeCents = ((int) $matches[1]) * 100;
        $fractionCents = (int) str_pad($matches[2] ?? '', 2, '0');

        return $wholeCents + $fractionCents;
    }
}
