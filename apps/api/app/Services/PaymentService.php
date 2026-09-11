<?php

namespace App\Services;

use App\Models\Invoice;
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
    public function __construct(
        private readonly ReceiptService $receiptService,
        private readonly FinanceAuthorizationService $authorization,
    ) {
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
        $invoice = Invoice::query()
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->first();
        $this->validatePayableOrder($order, $user, $invoice);
        [$paidCents, $totalCents, $amountCents] = $this->paymentBalances($order, $invoice, $data['amount']);

        if ($amountCents > $totalCents - $paidCents) {
            throw ValidationException::withMessages([
                'amount' => 'Payment cannot exceed the outstanding balance.',
            ]);
        }

        $payment = $this->createPaymentRecord($order, $data, $amountCents);
        $newPaidCents = $paidCents + $amountCents;
        $this->applyPaymentToOrder($order, $newPaidCents, $totalCents);
        if ($invoice) {
            $this->reconcileInvoice($invoice, $order->id, $totalCents);
        }

        return ['payment' => $payment->load('order'), 'created' => true];
    }

    private function validatePayableOrder(Order $order, User $user, ?Invoice $invoice = null): void
    {
        $this->authorizeOrder($order, $user);
        if (!in_array($order->status, ['Delivered', 'Partially Paid'], true)) {
            throw ValidationException::withMessages([
                'order_id' => "Payments can only be recorded for delivered orders. Current status: {$order->status}.",
            ]);
        }
        if ($invoice && in_array($invoice->status, [Invoice::PAID, Invoice::CANCELLED], true)) {
            throw ValidationException::withMessages([
                'order_id' => "Payments cannot be recorded for a {$invoice->status} invoice.",
            ]);
        }
    }

    /** @return array{int, int, int} */
    private function paymentBalances(Order $order, ?Invoice $invoice, mixed $amount): array
    {
        $paidCents = $this->validCompletedPaymentCents($order->id);
        $totalCents = $this->moneyToCents($invoice?->total_amount ?? $order->total_amount);

        return [$paidCents, $totalCents, $this->moneyToCents($amount)];
    }

    private function validCompletedPaymentCents(int $orderId): int
    {
        return $this->moneyToCents(
            Payment::query()
                ->where('order_id', $orderId)
                ->completedPositive()
                ->sum('amount')
        );
    }

    private function reconcileInvoice(Invoice $invoice, int $orderId, int $totalCents): void
    {
        $paidCents = $this->validCompletedPaymentCents($orderId);
        if ($paidCents > $totalCents) {
            throw ValidationException::withMessages([
                'amount' => 'Payment cannot exceed the invoice outstanding balance.',
            ]);
        }
        $balanceCents = $totalCents - $paidCents;
        $invoice->update([
            'paid_amount' => number_format($paidCents / 100, 2, '.', ''),
            'balance_amount' => number_format($balanceCents / 100, 2, '.', ''),
            'status' => $paidCents === $totalCents ? Invoice::PAID : Invoice::PARTIALLY_PAID,
        ]);
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
        if ((string) $payment->payment_method !== (string) $data['payment_method']) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This payment request identity was already used with a different payment method.',
            ]);
        }

        return ['payment' => $payment, 'created' => false];
    }

    private function authorizeOrder(Order $order, User $user): void
    {
        if ($this->authorization->isAdmin($user) || $this->authorization->isFinance($user)) {
            return;
        }

        if (!$this->authorization->hasCurrentRole($user, 'outlet')
            || !$user->outlet
            || (int) $user->outlet->id !== (int) $order->outlet_id) {
            abort(403, 'You are not authorized to record payment for this order.');
        }
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
