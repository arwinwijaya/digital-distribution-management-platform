<?php

namespace App\Services;

use App\Models\InvoiceReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InvoiceReminderStateService
{
    public function claimLocked(InvoiceReminder $reminder, Carbon $now): ?InvoiceReminder
    {
        if (in_array($reminder->status, [InvoiceReminder::SENT, InvoiceReminder::FAILED, InvoiceReminder::SUPPRESSED], true)) {
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

    public function suppressClosedRetry(InvoiceReminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
            if (! $locked || in_array($locked->status, [InvoiceReminder::SENT, InvoiceReminder::FAILED, InvoiceReminder::SUPPRESSED], true)) {
                return;
            }
            $locked->update([
                'status' => InvoiceReminder::SUPPRESSED,
                'sent_at' => null,
                'claimed_at' => null,
                'claim_token' => null,
                'next_attempt_at' => null,
                'last_error' => 'Suppressed: invoice closed before retry.',
            ]);
        });
    }

    public function suppressClaim(InvoiceReminder $reminder): void
    {
        DB::transaction(function () use ($reminder): void {
            $locked = InvoiceReminder::query()->lockForUpdate()->find($reminder->id);
            if (! $locked || $locked->status !== InvoiceReminder::SENDING || $locked->claim_token !== $reminder->claim_token) {
                return;
            }
            $locked->update([
                'status' => InvoiceReminder::SUPPRESSED,
                'sent_at' => null,
                'claimed_at' => null,
                'claim_token' => null,
                'next_attempt_at' => null,
                'last_error' => 'Suppressed: invoice closed before send.',
            ]);
        });
    }

    /** @param array<string, mixed> $response */
    public function markSent(InvoiceReminder $reminder, array $response, Carbon $now): bool
    {
        return DB::transaction(function () use ($reminder, $response, $now): bool {
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
    }

    public function recordFailure(InvoiceReminder $reminder, string $error, Carbon $now): bool
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

    public function sendLeaseSeconds(): int
    {
        return max(1, (int) config('whatsapp.send_lease_seconds', 300));
    }

    /** @return array<int, int> */
    private function backoffMinutes(): array
    {
        $configured = config('whatsapp.reminder_schedule.backoff_minutes', [1, 5, 15]);
        if (is_array($configured) && $configured !== []) {
            return array_values(array_map('intval', $configured));
        }

        return [1, 5, 15];
    }

    private function maxAttempts(): int
    {
        return 1 + count($this->backoffMinutes());
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
}
