<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceReminderService
{
    public const TIMEZONE = InvoiceReminderCandidateSelector::TIMEZONE;

    public function __construct(
        private readonly WhatsAppOutboundService $outbound,
        private readonly InvoiceReminderCandidateSelector $candidates,
        private readonly InvoiceReminderClaimService $claims,
        private readonly InvoiceReminderStateService $state,
    ) {}

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
        foreach ($this->candidates->candidates($now) as $candidate) {
            $this->claimAndSend(
                $candidate['invoice'],
                $candidate['event'],
                $candidate['event_date'],
                $now,
                $outcomes,
            );
        }
    }

    private function processRetries(Carbon $now, array &$outcomes): void
    {
        $leaseExpiry = $now->copy()->subSeconds($this->state->sendLeaseSeconds());
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

    private function claimAndSend(
        Invoice $invoice,
        string $event,
        string $eventDate,
        Carbon $now,
        array &$outcomes,
    ): void {
        if (! $this->candidates->isEligible($invoice)) {
            $outcomes['skipped']++;

            return;
        }

        $claim = $this->claims->claim($invoice, $event, $eventDate, $now);
        $reminder = $claim['reminder'];
        if (! $reminder) {
            return;
        }
        if ($claim['created']) {
            $outcomes['created']++;
        }
        $this->attemptSend($reminder, $now, $outcomes);
    }

    private function retryReminder(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $invoice = $reminder->invoice()->with('outlet')->first();
        if (! $invoice || ! $this->candidates->isEligible($invoice)) {
            $this->state->suppressClosedRetry($reminder);
            $outcomes['skipped']++;

            return;
        }

        $claimed = DB::transaction(function () use ($reminder, $now): ?InvoiceReminder {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);

            return $locked ? $this->state->claimLocked($locked, $now) : null;
        });
        if (! $claimed) {
            return;
        }

        $outcomes['retries']++;
        $this->attemptSend($claimed, $now, $outcomes);
    }

    private function attemptSend(InvoiceReminder $reminder, Carbon $now, array &$outcomes): void
    {
        $invoice = $reminder->invoice()->with('outlet')->first();
        if (! $invoice || ! $this->candidates->isEligible($invoice)) {
            $this->state->suppressClaim($reminder);
            $outcomes['skipped']++;

            return;
        }

        try {
            $response = $this->sendReminderText($invoice, $reminder);
            if ($this->state->markSent($reminder, $response, $now)) {
                $outcomes['sent']++;
            }
        } catch (Throwable $exception) {
            if ($this->state->recordFailure($reminder, $exception->getMessage(), $now)) {
                $outcomes['failed']++;
            }
        }
    }

    /** @return array<string, mixed> */
    private function sendReminderText(Invoice $invoice, InvoiceReminder $reminder): array
    {
        $phone = $invoice->outlet?->phone ?? '';
        $body = "Invoice {$invoice->invoice_number} due {$invoice->due_date} reminder ({$reminder->event_type}).";

        return $this->outbound->sendInvoiceReminder($phone, $body, (string) $reminder->idempotency_key);
    }

    private function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }
}
