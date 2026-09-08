<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\User;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly ReceiptService $receiptService)
    {
    }

    /**
     * Record one completed payment while serializing all balance changes for an order.
     * The idempotency key is unique in the database and replayed requests are safe.
     *
     * @return array{payment: Payment, created: bool}
     */
    public function record(array $data, User $user): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(fn () => $this->recordInTransaction($data, $user));
            } catch (QueryException $exception) {
                $existing = $this->findCommittedPayment($data['idempotency_key']);
                if ($existing) {
                    return $this->replayExistingPayment($existing, $data, $user);
                }

                if ($attempt === 2) {
                    throw $exception;
                }
                usleep(10000 * ($attempt + 1));
            }
        }

        throw new \LogicException('Unable to record payment.');
    }

    /**
     * Execute the payment workflow inside the caller's transaction.
     *
     * Lock order is deliberate: outlet first, then order. The idempotency
     * lookup remains after both locks so a waiting request replays the winner.
     *
     * @return array{payment: Payment, created: bool}
     */
    private function recordInTransaction(array $data, User $user): array
    {
        ConcurrencyTestBarrier::await('payment');
        $order = $this->lockOrderForPayment($data['order_id']);
        $existing = $this->findPaymentForUpdate($data['idempotency_key']);

        if ($existing) {
            return $this->replayExistingPayment($existing, $data, $user);
        }

        return $this->createPaymentForOrder($order, $data, $user);
    }

    private function lockOrderForPayment(int|string $orderId): Order
    {
        // Serialize payment updates with credit-consuming order submissions.
        $orderOwner = Order::whereKey($orderId)->firstOrFail()->outlet_id;
        Outlet::whereKey($orderOwner)->lockForUpdate()->firstOrFail();

        // The order lock makes the paid/remaining calculation safe for races.
        return Order::lockForUpdate()->findOrFail($orderId);
    }

    private function findPaymentForUpdate(string $idempotencyKey): ?Payment
    {
        return Payment::where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
    }

    private function findCommittedPayment(string $idempotencyKey): ?Payment
    {
        // A unique-key conflict may occur after another transaction wins between
        // our lookup and insert. Read back that committed winner when available.
        try {
            return Payment::where('idempotency_key', $idempotencyKey)->first();
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * @return array{payment: Payment, created: bool}
     */
    private function createPaymentForOrder(Order $order, array $data, User $user): array
    {
        $this->validatePayableOrder($order, $user);
        [$paidCents, $totalCents, $amountCents] = $this->paymentBalances($order, $data['amount']);

        if ($amountCents > $totalCents - $paidCents) {
            throw ValidationException::withMessages([
                'amount' => 'Payment cannot exceed the order outstanding balance.',
            ]);
        }

        $payment = $this->createPaymentRecord($order, $data, $amountCents);
        $this->applyPaymentToOrder($order, $paidCents + $amountCents, $totalCents);

        return ['payment' => $payment->load('order'), 'created' => true];
    }

    private function validatePayableOrder(Order $order, User $user): void
    {
        $this->authorizeOrder($order, $user);
        if (!in_array($order->status, ['Delivered', 'Partially Paid'], true)) {
            throw ValidationException::withMessages([
                'order_id' => "Payments can only be recorded for delivered orders. Current status: {$order->status}.",
            ]);
        }
    }

    /** @return array{int, int, int} */
    private function paymentBalances(Order $order, mixed $amount): array
    {
        $paidCents = $this->moneyToCents(
            Payment::where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount')
        );

        return [
            $paidCents,
            $this->moneyToCents($order->total_amount),
            $this->moneyToCents($amount),
        ];
    }

    private function createPaymentRecord(Order $order, array $data, int $amountCents): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => number_format($amountCents / 100, 2, '.', ''),
            'payment_method' => $data['payment_method'],
            'status' => 'completed',
            'idempotency_key' => $data['idempotency_key'],
            'receipt_reference' => null,
        ]);
        $payment->receipt_reference = $this->receiptService->generate($payment);
        $payment->save();

        return $payment;
    }

    private function applyPaymentToOrder(Order $order, int $paidCents, int $totalCents): void
    {
        $order->paid_amount = number_format($paidCents / 100, 2, '.', '');
        $order->status = $paidCents === $totalCents ? 'Paid' : 'Partially Paid';
        $order->save();
        $order->recordStatus($order->status, 'Payment recorded');
    }

    /**
     * Validate and replay a payment already committed for this request identity.
     *
     * @return array{payment: Payment, created: bool}
     */
    private function replayExistingPayment(Payment $payment, array $data, User $user): array
    {
        $payment->load('order');
        $order = $payment->order;
        if (!$order) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This payment request identity is not associated with a valid order.',
            ]);
        }

        $this->authorizeOrder($order, $user);
        if ((int) $payment->order_id !== (int) $data['order_id']) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This payment request identity was already used for another order.',
            ]);
        }
        if ($this->moneyToCents($payment->amount) !== $this->moneyToCents($data['amount'])) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This payment request identity was already used with a different amount.',
            ]);
        }

        return ['payment' => $payment, 'created' => false];
    }

    private function authorizeOrder(Order $order, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if (!$user->isOutlet() || !$user->outlet || (int) $user->outlet->id !== (int) $order->outlet_id) {
            abort(403, 'You are not authorized to record payment for this order.');
        }
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
