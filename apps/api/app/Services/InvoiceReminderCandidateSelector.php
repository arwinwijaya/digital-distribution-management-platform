<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Support\Carbon;

final class InvoiceReminderCandidateSelector
{
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * @return iterable<int, array{invoice: Invoice, event: string, event_date: string}>
     */
    public function candidates(Carbon $now): iterable
    {
        $today = $now->copy()->timezone(self::TIMEZONE)->startOfDay();
        $hMinusOneDate = $today->copy()->addDay()->toDateString();

        $candidates = Invoice::query()
            ->whereIn('status', [Invoice::UNPAID, Invoice::PARTIALLY_PAID])
            ->whereDate('due_date', '<=', $hMinusOneDate)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $invoice) {
            $dueDate = Carbon::parse((string) $invoice->getRawOriginal('due_date'), self::TIMEZONE)->startOfDay();
            $event = $this->eventFor($invoice, $dueDate, $today, $now);
            if ($event !== null) {
                yield [
                    'invoice' => $invoice,
                    'event' => $event['event'],
                    'event_date' => $event['event_date'],
                ];
            }
        }
    }

    public function isEligible(Invoice $invoice): bool
    {
        return in_array($invoice->status, [Invoice::UNPAID, Invoice::PARTIALLY_PAID], true);
    }

    /** @return array{event: string, event_date: string}|null */
    private function eventFor(Invoice $invoice, Carbon $dueDate, Carbon $today, Carbon $now): ?array
    {
        $eventDate = $dueDate->toDateString();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $todayDate = $today->toDateString();

        if ($eventDate === $tomorrow) {
            return ['event' => InvoiceReminder::EVENT_H_MINUS_ONE, 'event_date' => $eventDate];
        }

        // Recovery is open-ended from the due date through the first eligible
        // run. This covers a scheduler outage of any length, not just yesterday.
        if ($eventDate <= $todayDate && ! $this->hasHMinusOne($invoice->id, $eventDate)) {
            return ['event' => InvoiceReminder::EVENT_H_MINUS_ONE, 'event_date' => $eventDate];
        }

        if ($dueDate->lte($today)) {
            // A due-date reminder is inclusive at 00:00 Asia/Jakarta, while a
            // run before that boundary must not emit an overdue event.
            if ($dueDate->equalTo($today) && $now->timezone(self::TIMEZONE)->lt($dueDate)) {
                return null;
            }

            return ['event' => InvoiceReminder::EVENT_OVERDUE, 'event_date' => $eventDate];
        }

        return null;
    }

    private function hasHMinusOne(int $invoiceId, string $eventDate): bool
    {
        return InvoiceReminder::query()
            ->where('invoice_id', $invoiceId)
            ->where('event_type', InvoiceReminder::EVENT_H_MINUS_ONE)
            ->whereDate('event_date', $eventDate)
            ->exists();
    }
}
