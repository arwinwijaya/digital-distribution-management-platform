<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class InvoiceReminderClaimService
{
    public function __construct(private readonly InvoiceReminderStateService $state) {}

    /** @return array{reminder: InvoiceReminder|null, created: bool} */
    public function claim(
        Invoice $invoice,
        string $event,
        string $eventDate,
        Carbon $now,
    ): array {
        $logicalKey = "invoice-reminder:{$invoice->id}:{$event}:{$eventDate}";
        $idempotencyKey = hash('sha256', $logicalKey);

        try {
            return DB::transaction(function () use ($invoice, $event, $eventDate, $logicalKey, $idempotencyKey, $now): array {
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

                return [
                    'reminder' => $locked ? $this->state->claimLocked($locked, $now) : null,
                    'created' => $created,
                ];
            });
        } catch (QueryException $exception) {
            Log::error('Invoice reminder claim transaction failed.', [
                'invoice_id' => $invoice->id,
                'event' => $event,
                'event_date' => $eventDate,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
