<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Record one completed payment while serializing all balance changes for an order.
     * The idempotency key is unique in the database and replayed requests are safe.
     *
     * @return array{payment: Payment, created: bool}
     */
    public function record(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            $existing = Payment::where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->load('order');
                $this->authorizeOrder($existing->order, $user);
                if ((int) $existing->order_id !== (int) $data['order_id']) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This payment request identity was already used for another order.',
                    ]);
                }
                if ($this->moneyToCents($existing->amount) !== $this->moneyToCents($data['amount'])) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This payment request identity was already used with a different amount.',
                    ]);
                }

                return ['payment' => $existing, 'created' => false];
            }

            // Serialize payment updates with credit-consuming order submissions
            // by taking the same outlet lock before the order lock.
            $orderOwner = Order::whereKey($data['order_id'])->firstOrFail()->outlet_id;
            Outlet::whereKey($orderOwner)->lockForUpdate()->firstOrFail();
            // The order lock makes the paid/remaining calculation safe against
            // concurrent payment requests for the same order.
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);
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
                'receipt_reference' => 'RCT-'.strtoupper(bin2hex(random_bytes(6))),
            ]);

            $newPaidCents = $paidCents + $amountCents;
            $order->paid_amount = number_format($newPaidCents / 100, 2, '.', '');
            $order->status = $newPaidCents === $totalCents ? 'Paid' : 'Partially Paid';
            $order->save();
            $order->recordStatus($order->status, 'Payment recorded');

            return ['payment' => $payment->load('order'), 'created' => true];
        });
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
