<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InvoiceMetricsService
{
    /** @var array<int, string> */
    private const ACTIVE_STATUSES = [Invoice::UNPAID, Invoice::PARTIALLY_PAID];

    /**
     * Return bounded database aggregates for finance metrics. Event aggregates
     * use the requested window; current-state aggregates intentionally do not.
     *
     * @return array<string, mixed>
     */
    public function metrics(CarbonInterface $start, CarbonInterface $end, CarbonInterface $asOf, ?int $outletId = null): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $invoices = fn (): Builder => $this->invoiceQuery($outletId);

        $issued = $invoices()
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->count();

        // Outstanding is a current-state measure: do not apply the event window.
        $outstanding = $invoices()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->sum('balance_amount');

        // Paid invoices are no longer active for overdue purposes; cancelled
        // invoices are excluded from both numerator and denominator.
        $activeCount = $invoices()->whereIn('status', self::ACTIVE_STATUSES)->count();
        $overdueCount = $invoices()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereDate('due_date', '<', $asOf->toDateString())
            ->count();

        $statusCounts = $invoices()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        $collection = $this->collectionTime($invoices(), $start, $end);
        $reminders = $this->reminderCounts($startDate, $endDate, $outletId);

        return [
            'window' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'issued_invoices' => [
                'count' => (int) $issued,
            ],
            'outstanding_balance' => [
                'amount' => $this->number($outstanding),
            ],
            'overdue_rate' => [
                'rate' => $activeCount > 0 ? round(($overdueCount / $activeCount) * 100, 2) : 0,
                'overdue_count' => (int) $overdueCount,
                'active_count' => (int) $activeCount,
            ],
            'collection_time' => $collection,
            'payment_status_breakdown' => collect(Invoice::statuses())
                ->mapWithKeys(fn (string $status): array => [$status => (int) ($statusCounts[$status] ?? 0)])
                ->all(),
            'reminders' => [
                'success' => $reminders['sent'],
                'failure' => $reminders['failed'],
                'sent' => $reminders['sent'],
                'failed' => $reminders['failed'],
            ],
        ];
    }

    private function invoiceQuery(?int $outletId = null): Builder
    {
        return Invoice::query()->when($outletId !== null, fn (Builder $query) => $query->where('outlet_id', $outletId));
    }

    /** @return array{average_days: int|float, fully_collected_count: int} */
    private function collectionTime(Builder $invoices, CarbonInterface $start, CarbonInterface $end): array
    {
        $latestPayments = Payment::query()
            ->select('order_id')
            ->selectRaw('MAX(payments.created_at) as final_payment_at')
            ->where('payments.status', 'completed')
            ->where('payments.amount', '>', 0)
            ->groupBy('order_id');

        $driver = DB::connection()->getDriverName();
        $difference = match ($driver) {
            'mysql', 'mariadb' => 'DATEDIFF(latest_payments.final_payment_at, invoices.issue_date)',
            'pgsql' => "EXTRACT(EPOCH FROM (latest_payments.final_payment_at::timestamp - invoices.issue_date::timestamp)) / 86400",
            default => 'julianday(date(latest_payments.final_payment_at)) - julianday(date(invoices.issue_date))',
        };

        $row = $invoices
            ->joinSub($latestPayments, 'latest_payments', function ($join): void {
                $join->on('latest_payments.order_id', '=', 'invoices.order_id');
            })
            ->where('invoices.status', Invoice::PAID)
            ->whereBetween('latest_payments.final_payment_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw("COALESCE(AVG({$difference}), 0) as average_days, COUNT(invoices.id) as fully_collected_count")
            ->first();

        $average = round((float) ($row->average_days ?? 0), 2);

        return [
            'average_days' => $average == 0.0 ? 0 : $average,
            'fully_collected_count' => (int) ($row->fully_collected_count ?? 0),
        ];
    }

    /** @return array{sent: int, failed: int} */
    private function reminderCounts(string $startDate, string $endDate, ?int $outletId): array
    {
        $counts = InvoiceReminder::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_reminders.invoice_id')
            ->when($outletId !== null, fn (Builder $query) => $query->where('invoices.outlet_id', $outletId))
            ->whereBetween('invoice_reminders.event_date', [$startDate, $endDate])
            ->whereIn('invoice_reminders.status', [InvoiceReminder::SENT, InvoiceReminder::FAILED])
            ->select('invoice_reminders.status')
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy('invoice_reminders.status')
            ->pluck('aggregate_count', 'status');

        return [
            'sent' => (int) ($counts[InvoiceReminder::SENT] ?? 0),
            'failed' => (int) ($counts[InvoiceReminder::FAILED] ?? 0),
        ];
    }

    private function number(mixed $value): int|float
    {
        $number = round((float) ($value ?? 0), 2);
        return $number == 0.0 ? 0 : $number;
    }
}
