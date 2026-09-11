<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceReminderService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(private readonly WhatsAppClient $client) {}

    public function process(): array
    {
        $now = $this->now();
        $outcomes = ['created' => 0, 'retries' => 0, 'skipped' => 0, 'sent' => 0, 'failed' => 0];

        $this->processDueReminders($now, $outcomes);
        $this->processRetries($now, $outcomes);

        return $outcomes;
    }

    private function processDueReminders(Carbon $now, array &$outcomes): void
    {
        $today = $now->copy()->timezone(self::TIMEZONE)->startOfDay();
        $hMinusOneDate = $today->copy()->addDay()->toDateString();
        $overdueDate = $today->toDateString();

        $dueCandidates = Invoice::query()
            ->whereIn('status', [Invoice::UNPAID, Invoice::PARTIALLY_PAID])
            ->where('due_date', '<=', $hMinusOneDate)
            ->orderBy('id')
            ->get();

        $hMinusOneCandidates = Invoice::query()
            ->whereIn('status', [Invoice::UNPAID, Invoice::PARTIALLY_PAID])
            ->whereDate('due_date', $hMinusOneDate)
            ->orderBy('id')
            ->get();

        foreach ($dueCandidates->merge($hMinusOneCandidates)->unique('id') as $invoice) {
            $dueDate = Carbon::parse($invoice->due_date)->timezone(self::TIMEZONE)->startOfDay();
            $event = $this->eventFor($invoice, $dueDate, $today, $now);
            if ($event === null) {
                continue;
            }
            $this->claimAndSend($invoice->fresh(), $event['event'], $event['event_date'], $now, $outcomes);
        }
    }

    /**
     * @return array{event: string, event_date: string}|null
     */
    private function eventFor(Invoice $invoice, Carbon $dueDate, Carbon $today, Carbon $now): ?array
    {
        $eventDate = $dueDate->toDateString();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $yesterday = $today->copy()->subDay()->toDateString();

        if ($eventDate === $tomorrow) {
            return ['event' => InvoiceReminder::EVENT_H_MINUS_ONE, 'event_date' => $eventDate];
        }

        // Missed H-1 recovery: due yesterday but no H-1 was ever recorded for THIS invoice.
        if ($eventDate === $yesterday && ! $this->hasHMinusOne($invoice->id, $eventDate)) {
            return ['event' => InvoiceReminder::EVENT_H_MINUS_ONE, 'event_date' => $eventDate];
        }

        if ($dueDate->lte($today)) {
            if ($dueDate->equalTo($today)) {
                $startOfDueDay = $dueDate->copy()->startOfDay();
                if ($now->lt($startOfDueDay)) {
                    return null;
                }
            }

            return ['event' => InvoiceReminder::EVENT_OVERDUE, 'event_date' => $eventDate];
        }

        return null;
    }

    private function hasHMinusOne(int $invoiceId, string $eventDate): bool
    {
        return InvoiceReminder::where('invoice_id', $invoiceId)
            ->where('event_type', InvoiceReminder::EVENT_H_MINUS_ONE)
            ->whereDate('event_date', $eventDate)
            ->exists();
    }

    private function processRetries(Carbon $now, array &$outcomes): void
    {
        $due = InvoiceReminder::query()
            ->where('status', InvoiceReminder::PENDING)
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', $now)
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($due as $reminder) {
            $this->retryReminder($reminder->fresh(), $now, $outcomes);
        }
    }

    private function claimAndSend(Invoice $invoice, string $event, string $eventDate, Carbon $now, array &$outcomes): void
    {
        if (! $this->isEligible($invoice)) {
            $outcomes['skipped']++;

            return;
        }
        if (InvoiceReminder::where('invoice_id', $invoice->id)
            ->where('event_type', $event)
            ->whereDate('event_date', $eventDate)
            ->exists()) {
            return;
        }

        $logicalKey = $this->logicalKey($invoice->id, $event, $eventDate);
        $idempotencyKey = hash('sha256', $logicalKey);

        try {
            $reminder = DB::transaction(function () use ($invoice, $event, $eventDate, $logicalKey, $idempotencyKey, $now): InvoiceReminder {
                if (config('whatsapp.concurrency_barrier_enabled', false)) {
                    ConcurrencyTestBarrier::await('invoice-reminder');
                }
                DB::table('invoice_reminders')->insertOrIgnore([
                    'invoice_id' => $invoice->id,
                    'event_type' => $event,
                    'event_date' => $eventDate,
                    'status' => InvoiceReminder::PENDING,
                    'attempts' => 0,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => json_encode(['logical_key' => $logicalKey]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return InvoiceReminder::where('invoice_id', $invoice->id)
                    ->where('event_type', $event)
                    ->whereDate('event_date', $eventDate)
                    ->lockForUpdate()
                    ->firstOrFail();
            });
        } catch (Throwable $exception) {
            $outcomes['skipped']++;

            return;
        }

        if ($reminder->status === InvoiceReminder::SENT) {
            return;
        }
        if ($reminder->status === InvoiceReminder::FAILED) {
            return;
        }

        $outcomes['created']++;
        $this->attemptSend($reminder->fresh(), $now, $outcomes);
    }

    private function retryReminder(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $invoice = $reminder->invoice()->lockForUpdate()->first() ?? $reminder->invoice;
        if (! $invoice || ! $this->isEligible($invoice)) {
            DB::transaction(function () use ($reminder): void {
                $locked = InvoiceReminder::lockForUpdate()->find($reminder->id);
                if (! $locked || $locked->status !== InvoiceReminder::PENDING) {
                    return;
                }
                $locked->update([
                    'status' => InvoiceReminder::SENT,
                    'sent_at' => null,
                    'next_attempt_at' => null,
                    'last_error' => 'Suppressed: invoice closed before retry.',
                ]);
            });
            $outcomes['skipped']++;

            return;
        }

        $outcomes['retries']++;
        $this->attemptSend($reminder->fresh(), $now, $outcomes);
    }

    private function attemptSend(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $claimed = DB::transaction(function () use ($reminder): ?InvoiceReminder {
            $locked = InvoiceReminder::lockForUpdate()->find($reminder->id);
            if (! $locked || $locked->status !== InvoiceReminder::PENDING) {
                return null;
            }
            if ($locked->next_attempt_at !== null && Carbon::parse($locked->next_attempt_at)->gt(now())) {
                return null;
            }
            $locked->update(['attempts' => $locked->attempts + 1]);

            return $locked->fresh();
        });

        if (! $claimed) {
            return;
        }

        $invoice = $claimed->invoice()->with('outlet')->first();
        if (! $invoice || ! $this->isEligible($invoice)) {
            $claimed->update([
                'status' => InvoiceReminder::SENT,
                'sent_at' => null,
                'next_attempt_at' => null,
                'last_error' => 'Suppressed: invoice closed before send.',
            ]);
            $outcomes['skipped']++;

            return;
        }

        try {
            $response = $this->sendReminderText($invoice, $claimed);
            $claimed->update([
                'status' => InvoiceReminder::SENT,
                'sent_at' => $now,
                'next_attempt_at' => null,
                'provider_message_id' => $this->providerIdFromResponse($response),
                'last_error' => null,
            ]);
            $outcomes['sent']++;
        } catch (Throwable $exception) {
            $this->recordFailure($claimed->fresh(), $exception->getMessage(), $now);
            $outcomes['failed']++;
        }
    }

    private function recordFailure(InvoiceReminder $reminder, string $error, Carbon $now): void
    {
        $maxAttempts = $this->maxAttempts();
        $attempts = (int) $reminder->attempts;
        if ($attempts >= $maxAttempts) {
            $reminder->update([
                'status' => InvoiceReminder::FAILED,
                'failed_at' => $now,
                'next_attempt_at' => null,
                'last_error' => $error,
            ]);

            return;
        }

        $backoff = $this->backoffMinutes();
        $delay = $backoff[min($attempts - 1, count($backoff) - 1)] ?? end($backoff);
        // Anchor the schedule on the first attempt (row creation) so retries land on
        // original+backoff[n] (07:00 -> 07:01, 07:05, 07:15) instead of drifting from each retry time.
        $anchor = $reminder->created_at ? $reminder->created_at->copy() : $now->copy();
        $reminder->update([
            'status' => InvoiceReminder::PENDING,
            'next_attempt_at' => $anchor->addMinutes((int) $delay),
            'last_error' => $error,
        ]);
    }

    /** @return array<string, mixed> */
    private function sendReminderText(Invoice $invoice, InvoiceReminder $reminder): array
    {
        $phone = $invoice->outlet?->phone ?? '';
        $body = "Invoice {$invoice->invoice_number} due {$invoice->due_date} reminder ({$reminder->event_type}).";

        if (method_exists($this->client, 'sendTextWithIdempotency')) {
            /** @var array<string, mixed> $response */
            $response = $this->client->sendTextWithIdempotency($phone, $body, (string) $reminder->idempotency_key);

            return $response;
        }

        return $this->client->sendText($phone, $body);
    }

    /** @param array<string, mixed> $response */
    private function providerIdFromResponse(array $response): ?string
    {
        foreach (['provider_message_id', 'message_id', 'id'] as $key) {
            if (is_scalar($response[$key] ?? null) && trim((string) $response[$key]) !== '') {
                return trim((string) $response[$key]);
            }
        }

        return data_get($response, 'messages.0.id');
    }

    private function isEligible(Invoice $invoice): bool
    {
        return in_array($invoice->status, [Invoice::UNPAID, Invoice::PARTIALLY_PAID], true);
    }

    private function logicalKey(int $invoiceId, string $event, string $eventDate): string
    {
        return "invoice-reminder:{$invoiceId}:{$event}:{$eventDate}";
    }

    private function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /** @return array<int, int> */
    private function backoffMinutes(): array
    {
        $configured = config('whatsapp.reminder_backoff_minutes', config('whatsapp.reminder_schedule.backoff_minutes', [1, 5, 15]));
        if (is_array($configured) && $configured !== []) {
            return array_values(array_map('intval', $configured));
        }

        return [1, 5, 15];
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('whatsapp.reminder_max_attempts', config('whatsapp.reminder_schedule.max_attempts', 3)));
    }
}
