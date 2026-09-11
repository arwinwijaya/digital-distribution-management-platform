<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class InvoiceBackfillService
{
    private const ELIGIBLE_STATUSES = ['Delivered', 'Paid'];

    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    /**
     * Backfill legacy orders in bounded chunks. The callback receives each row
     * event as it is handled so command callers can keep failures visible.
     *
     * @return array{created: int, reused: int, skipped: int, failed: int}
     */
    public function backfill(int $chunkSize = 100, ?callable $onRow = null): array
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size must be at least 1.');
        }

        $summary = [
            'created' => 0,
            'reused' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        Order::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $orders) use (&$summary, $onRow): void {
                foreach ($orders as $order) {
                    if (! $this->isEligible($order)) {
                        $summary['skipped']++;
                        $this->report($onRow, 'skipped', $order);
                        continue;
                    }

                    try {
                        $created = false;
                        $invoice = DB::transaction(function () use ($order, &$created): Invoice {
                            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                            $invoice = Invoice::query()
                                ->where('order_id', $lockedOrder->id)
                                ->lockForUpdate()
                                ->first();

                            if ($invoice === null) {
                                if ($this->hasInvoiceNumberConflict($lockedOrder)) {
                                    throw new RuntimeException('The deterministic invoice number is already assigned to another order.');
                                }

                                $invoice = $this->invoiceService->createForApprovedOrder($lockedOrder);
                                $created = true;
                            }

                            return $this->synchronizeAmounts($invoice);
                        });

                        $event = $created ? 'created' : 'reused';
                        $summary[$event]++;
                        $this->report($onRow, $event, $order, $invoice);
                    } catch (Throwable $exception) {
                        $summary['failed']++;
                        $this->report($onRow, 'failed', $order, null, $exception);
                    }
                }
            });

        return $summary;
    }

    private function isEligible(Order $order): bool
    {
        return in_array($order->status, self::ELIGIBLE_STATUSES, true);
    }

    private function hasInvoiceNumberConflict(Order $order): bool
    {
        return Invoice::query()
            ->where('invoice_number', 'INV-'.Carbon::today()->format('Ymd').'-'.$order->id)
            ->where('order_id', '!=', $order->id)
            ->exists();
    }

    private function synchronizeAmounts(Invoice $invoice): Invoice
    {
        $paidCents = $this->moneyToCents(
            Payment::query()
                ->where('order_id', $invoice->order_id)
                ->where('status', 'completed')
                ->where('amount', '>', 0)
                ->sum('amount')
        );
        $totalCents = $this->moneyToCents($invoice->total_amount);
        $balanceCents = max(0, $totalCents - $paidCents);

        $status = $paidCents >= $totalCents && $totalCents > 0
            ? Invoice::PAID
            : ($paidCents > 0 ? Invoice::PARTIALLY_PAID : Invoice::UNPAID);

        $invoice->update([
            'paid_amount' => $this->formatCents($paidCents),
            'balance_amount' => $this->formatCents($balanceCents),
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }

    private function report(
        ?callable $onRow,
        string $event,
        Order $order,
        ?Invoice $invoice = null,
        ?Throwable $exception = null,
    ): void {
        if ($onRow !== null) {
            $onRow($event, $order, $invoice, $exception);
        }
    }
}
