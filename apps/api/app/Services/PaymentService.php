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
                return DB::transaction(function () use ($data, $user) {
                    // Test-only synchronization releases public race workers together
                    // before the same outlet/order critical section.
                    ConcurrencyTestBarrier::await('payment');

                    // Serialize payment updates with credit-consuming order submissions
                    // by taking the same outlet lock before the order lock.
                    $orderOwner = Order::whereKey($data['order_id'])->firstOrFail()->outlet_id;
                    Outlet::whereKey($orderOwner)->lockForUpdate()->firstOrFail();
                    // The order lock makes the paid/remaining calculation safe against
                    // concurrent payment requests for the same order.
                    $order = Order::lockForUpdate()->findOrFail($data['order_id']);

                    // The lookup must happen after the locks. A request that waited for
                    // the winner's transaction now sees its committed payment and replays it.
                    $existing = Payment::where('idempotency_key', $data['idempotency_key'])
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $this->replayExistingPayment($existing, $data, $user);
                    }

                    $this->authorizeOrder($order, $user);
                    if (!in_array($order->status, ['Delivered', 'Partially Paid'], true)) {
                        throw ValidationException::withMessages([
                            'order_id' => "Payments can only be recorded for delivered orders. Current status: {$order->status}.",
                        ]);
                    }

                    $paidCents = $this->moneyToCents(
                        Payment::where('order_id', $order->id)
                            ->where('status', 'completed')
                            ->sum('amount')
                    );
                    $totalCents = $this->moneyToCents($order->total_amount);
                    $amountCents = $this->moneyToCents($data['amount']);
                    $remainingCents = $totalCents - $paidCents;

                    if ($amountCents > $remainingCents) {
                        throw ValidationException::withMessages([
                            'amount' => 'Payment cannot exceed the order outstanding balance.',
                        ]);
                    }

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

                    $newPaidCents = $paidCents + $amountCents;
                    $order->paid_amount = number_format($newPaidCents / 100, 2, '.', '');
                    $order->status = $newPaidCents === $totalCents ? 'Paid' : 'Partially Paid';
                    $order->save();
                    $order->recordStatus($order->status, 'Payment recorded');

                    return ['payment' => $payment->load('order'), 'created' => true];
                });
            } catch (QueryException $exception) {
                // A unique-key conflict can happen after another transaction wins
                // between our lookup and insert. Read the committed winner and apply
                // the same identity validation instead of leaking a 500 response.
                try {
                    $existing = Payment::where('idempotency_key', $data['idempotency_key'])->first();
                } catch (QueryException) {
                    $existing = null;
                }
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
