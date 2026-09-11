<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class InvoiceReminderService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public function __construct(private readonly WhatsAppOutboundService $outbound) {}

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

        // Include every invoice through tomorrow. eventFor() decides whether it
        // is an H-1 reminder, a recovered H-1, or the first overdue reminder.
        $candidates = Invoice::query()
            ->whereIn('status', [Invoice::UNPAID, Invoice::PARTIALLY_PAID])
            ->whereDate('due_date', '<=', $hMinusOneDate)
            ->orderBy('id')
            ->get();

        foreach ($candidates as $invoice) {
            $dueDate = Carbon::parse((string) $invoice->getRawOriginal('due_date'), self::TIMEZONE)->startOfDay();
            $event = $this->eventFor($invoice, $dueDate, $today, $now);
            if ($event === null) {
                continue;
            }
            $this->claimAndSend($invoice, $event['event'], $event['event_date'], $now, $outcomes);
        }
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

    private function processRetries(Carbon $now, array &$outcomes): void
    {
        $leaseExpiry = $now->copy()->subSeconds($this->sendLeaseSeconds());
        $due = InvoiceReminder::query()
            ->where(function ($query) use ($now, $leaseExpiry): void {
                $query->where(function ($pending) use ($now): void {
                    $pending->where('status', InvoiceReminder::PENDING)
                        ->whereNotNull('next_attempt_at')
                        ->where('next_attempt_at', '<=', $now);
                })->orWhere(function ($sending) use ($leaseExpiry): void {
                    $sending->where('status', InvoiceReminder::SENDING)
                        ->whereNotNull('claimed_at')
                        ->where('claimed_at', '<=', $leaseExpiry);
                });
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($due as $reminder) {
            $this->retryReminder($reminder, $now, $outcomes);
        }
    }

    private function claimAndSend(Invoice $invoice, string $event, string $eventDate, Carbon $now, array &$outcomes): void
    {
        if (! $this->isEligible($invoice)) {
            $outcomes['skipped']++;

            return;
        }

        $logicalKey = $this->logicalKey($invoice->id, $event, $eventDate);
        $idempotencyKey = hash('sha256', $logicalKey);
        $created = false;

        try {
            $reminder = DB::transaction(function () use ($invoice, $event, $eventDate, $logicalKey, $idempotencyKey, $now, &$created): ?InvoiceReminder {
                if (config('whatsapp.concurrency_barrier_enabled', false)) {
                    ConcurrencyTestBarrier::await('invoice-reminder');
                }

                $created = DB::table('invoice_reminders')->insertOrIgnore([
                    'invoice_id' => $invoice->id,
                    'event_type' => $event,
                    'event_date' => $eventDate,
                    'status' => InvoiceReminder::PENDING,
                    'attempts' => 0,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => json_encode(['logical_key' => $logicalKey]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]) === 1;

                $locked = InvoiceReminder::query()
                    ->where('invoice_id', $invoice->id)
                    ->where('event_type', $event)
                    ->whereDate('event_date', $eventDate)
                    ->lockForUpdate()
                    ->first();

                return $locked ? $this->claimLocked($locked, $now) : null;
            });
        } catch (Throwable) {
            $outcomes['skipped']++;

            return;
        }

        if (! $reminder) {
            return;
        }
        if ($created) {
            $outcomes['created']++;
        }
        $this->attemptSend($reminder, $now, $outcomes);
    }

    private function retryReminder(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $invoice = $reminder->invoice()->with('outlet')->first();
        if (! $invoice || ! $this->isEligible($invoice)) {
            DB::transaction(function () use ($reminder): void {
                $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
                if (! $locked || in_array($locked->status, [InvoiceReminder::SENT, InvoiceReminder::FAILED], true)) {
                    return;
                }
                $locked->update([
                    'status' => InvoiceReminder::SENT,
                    'sent_at' => null,
                    'claimed_at' => null,
                    'claim_token' => null,
                    'next_attempt_at' => null,
                    'last_error' => 'Suppressed: invoice closed before retry.',
                ]);
            });
            $outcomes['skipped']++;

            return;
        }

        $claimed = DB::transaction(function () use ($reminder, $now): ?InvoiceReminder {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);

            return $locked ? $this->claimLocked($locked, $now) : null;
        });
        if (! $claimed) {
            return;
        }

        $outcomes['retries']++;
        $this->attemptSend($claimed, $now, $outcomes);
    }

    private function claimLocked(InvoiceReminder $reminder, Carbon $now): ?InvoiceReminder
    {
        if (in_array($reminder->status, [InvoiceReminder::SENT, InvoiceReminder::FAILED], true)) {
            return null;
        }
        if ($reminder->status === InvoiceReminder::SENDING
            && $reminder->claimed_at !== null
            && $reminder->claimed_at->gt($now->copy()->subSeconds($this->sendLeaseSeconds()))) {
            return null;
        }
        if ($reminder->status === InvoiceReminder::PENDING
            && $reminder->next_attempt_at !== null
            && $reminder->next_attempt_at->gt($now)) {
            return null;
        }

        $reminder->update([
            'status' => InvoiceReminder::SENDING,
            'claimed_at' => $now,
            'claim_token' => (string) Str::uuid(),
            'attempts' => (int) $reminder->attempts + 1,
        ]);

        return $reminder->fresh();
    }

    private function attemptSend(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $invoice = $reminder->invoice()->with('outlet')->first();
        if (! $invoice || ! $this->isEligible($invoice)) {
            $this->suppressClaim($reminder);
            $outcomes['skipped']++;

            return;
        }

        try {
            $response = $this->sendReminderText($invoice, $reminder);
            $updated = DB::transaction(function () use ($reminder, $response, $now): bool {
                $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
                if (! $locked || $locked->status !== InvoiceReminder::SENDING || $locked->claim_token !== $reminder->claim_token) {
                    return false;
                }
                $locked->update([
                    'status' => InvoiceReminder::SENT,
                    'claimed_at' => null,
                    'claim_token' => null,
                    'sent_at' => $now,
                    'next_attempt_at' => null,
                    'provider_message_id' => $this->providerIdFromResponse($response),
                    'last_error' => null,
                ]);

                return true;
            });
            if ($updated) {
                $outcomes['sent']++;
            }
        } catch (Throwable $exception) {
            if ($this->recordFailure($reminder, $exception->getMessage(), $now)) {
                $outcomes['failed']++;
            }
        }
    }

    private function suppressClaim(InvoiceReminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
            if (! $locked || $locked->status !== InvoiceReminder::SENDING || $locked->claim_token !== $reminder->claim_token) {
                return;
            }
            $locked->update([
                'status' => InvoiceReminder::SENT,
                'sent_at' => null,
                'claimed_at' => null,
                'claim_token' => null,
                'next_attempt_at' => null,
                'last_error' => 'Suppressed: invoice closed before send.',
            ]);
        });
    }

    private function recordFailure(InvoiceReminder $reminder, string $error, Carbon $now): bool
    {
        return DB::transaction(function () use ($reminder, $error, $now): bool {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
            if (! $locked || $locked->status !== InvoiceReminder::SENDING || $locked->claim_token !== $reminder->claim_token) {
                return false;
            }

            $attempts = (int) $locked->attempts;
            if ($attempts >= $this->maxAttempts()) {
                $locked->update([
                    'status' => InvoiceReminder::FAILED,
                    'claimed_at' => null,
                    'claim_token' => null,
                    'failed_at' => $now,
                    'next_attempt_at' => null,
                    'last_error' => $error,
                ]);

                return true;
            }

            $backoff = $this->backoffMinutes();
            $delay = $backoff[$attempts - 1] ?? end($backoff);
            $anchor = $locked->created_at?->copy() ?? $now->copy();
            $locked->update([
                'status' => InvoiceReminder::PENDING,
                'claimed_at' => null,
                'claim_token' => null,
                'next_attempt_at' => $anchor->addMinutes((int) $delay),
                'last_error' => $error,
            ]);

            return true;
        });
    }

    /** @return array<string, mixed> */
    private function sendReminderText(Invoice $invoice, InvoiceReminder $reminder): array
    {
        $phone = $invoice->outlet?->phone ?? '';
        $body = "Invoice {$invoice->invoice_number} due {$invoice->due_date} reminder ({$reminder->event_type}).";

        return $this->outbound->sendInvoiceReminder($phone, $body, (string) $reminder->idempotency_key, [
            'invoice_id' => $invoice->id,
            'reminder_id' => $reminder->id,
        ]);
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
        // One initial attempt plus one attempt for every specified retry delay.
        return 1 + count($this->backoffMinutes());
    }

    private function sendLeaseSeconds(): int
    {
        return max(1, (int) config('whatsapp.send_lease_seconds', 300));
    }
}
