<?php

namespace Tests\Feature\Support;

use App\Contracts\WhatsAppClient;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Support\Carbon;

trait InvoiceReminderRetryScenarios
{
    public function test_paid_invoice_suppresses_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta'));

        [$order, $invoice] = $this->createDeliveredInvoice('paid-suppress', '2026-09-11');
        $invoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $invoice->total_amount,
            'balance_amount' => 0,
        ]);

        $this->artisan('invoices:reminders');

        $this->assertDatabaseCount('invoice_reminders', 0);

        Carbon::setTestNow();
    }

    public function test_cancelled_invoice_suppresses_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta'));

        [$order, $invoice] = $this->createDeliveredInvoice('cancelled-suppress', '2026-09-11');
        $invoice->update(['status' => Invoice::CANCELLED]);

        $this->artisan('invoices:reminders');

        $this->assertDatabaseCount('invoice_reminders', 0);

        Carbon::setTestNow();
    }

    public function test_provider_failure_schedules_idempotent_backoff(): void
    {
        $now = Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('backoff-identity', '2026-09-11');
        $client = $this->app->make(WhatsAppClient::class);

        $this->artisan('invoices:reminders');
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $this->assertSame(1, $reminder->attempts);
        $this->assertSame(InvoiceReminder::PENDING, $reminder->status);
        $this->assertSame($now->copy()->addMinutes(1)->toDateTimeString(), $reminder->next_attempt_at->toDateTimeString());

        $idempotencyKey = $reminder->idempotency_key;

        Carbon::setTestNow($now->copy()->addMinutes(1));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(2, $reminder->attempts);
        $this->assertSame($idempotencyKey, $reminder->idempotency_key);
        $this->assertSame($now->copy()->addMinutes(5)->toDateTimeString(), $reminder->next_attempt_at->toDateTimeString());

        Carbon::setTestNow($now->copy()->addMinutes(5));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(3, $reminder->attempts);
        $this->assertSame($idempotencyKey, $reminder->idempotency_key);
        $this->assertSame($now->copy()->addMinutes(15)->toDateTimeString(), $reminder->next_attempt_at->toDateTimeString());

        Carbon::setTestNow($now->copy()->addMinutes(15));
        $this->artisan('invoices:reminders');
        $reminder->refresh();
        $this->assertSame(4, $reminder->attempts);
        $this->assertSame($idempotencyKey, $reminder->idempotency_key);
        $this->assertSame(InvoiceReminder::FAILED, $reminder->status);
        $this->assertCount(4, $client->idempotencyKeys);
        $this->assertSame([$idempotencyKey, $idempotencyKey, $idempotencyKey, $idempotencyKey], $client->idempotencyKeys);

        Carbon::setTestNow();
    }

    public function test_final_reminder_failure_is_audited_without_invoice_mutation(): void
    {
        $now = Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('final-audit', '2026-09-11');
        $beforeStatus = $invoice->status;
        $beforeBalance = $invoice->balance_amount;
        $beforePaid = $invoice->paid_amount;

        $this->artisan('invoices:reminders');
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();

        Carbon::setTestNow($now->copy()->addMinutes(1));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        Carbon::setTestNow($now->copy()->addMinutes(5));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        Carbon::setTestNow($now->copy()->addMinutes(15));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        $this->assertSame(InvoiceReminder::FAILED, $reminder->status);
        $this->assertNotNull($reminder->failed_at);
        $this->assertNotNull($reminder->last_error);

        $invoice->refresh();
        $this->assertSame($beforeStatus, $invoice->status);
        $this->assertSame((string) $beforeBalance, (string) $invoice->balance_amount);
        $this->assertSame((string) $beforePaid, (string) $invoice->paid_amount);

        Carbon::setTestNow();
    }

    public function test_closed_invoice_suppresses_pending_retry(): void
    {
        $now = Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($now);

        [$order, $invoice] = $this->createDeliveredInvoice('closed-retry', '2026-09-11');
        $this->artisan('invoices:reminders');

        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->sole();
        $reminder->update([
            'status' => InvoiceReminder::PENDING,
            'next_attempt_at' => $now->copy()->addMinutes(1),
        ]);
        $beforeAttempts = $reminder->attempts;

        $invoice->update([
            'status' => Invoice::PAID,
            'paid_amount' => $invoice->total_amount,
            'balance_amount' => 0,
        ]);

        $client = $this->app->make(WhatsAppClient::class);
        $callsBeforeRetry = $client->sendTextCalls;

        Carbon::setTestNow($now->copy()->addMinutes(2));
        $this->artisan('invoices:reminders');
        $reminder->refresh();

        $this->assertSame(InvoiceReminder::SUPPRESSED, $reminder->status);
        $this->assertSame($beforeAttempts, $reminder->attempts);
        $this->assertSame($callsBeforeRetry, $client->sendTextCalls);

        Carbon::setTestNow();
    }
}
