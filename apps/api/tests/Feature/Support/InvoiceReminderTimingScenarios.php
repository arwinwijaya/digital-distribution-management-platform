<?php

namespace Tests\Feature\Support;

use App\Models\InvoiceReminder;
use Illuminate\Support\Carbon;

trait InvoiceReminderTimingScenarios
{
    public function test_invoice_reminder_is_sent_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 07:00:00', 'Asia/Jakarta'));

        [$order, $invoice] = $this->createDeliveredInvoice('h-minus-one', '2026-09-11');
        $client = $this->useSuccessfulReminderClient();

        $this->artisan('invoices:reminders');
        $this->artisan('invoices:reminders');

        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->first();
        $this->assertSame(InvoiceReminder::EVENT_H_MINUS_ONE, $reminder->event_type);
        $this->assertSame('2026-09-11', $reminder->event_date->toDateString());
        $this->assertSame(1, count($client->idempotencyKeys));
        $this->assertSame($reminder->idempotency_key, $client->idempotencyKeys[0]);

        Carbon::setTestNow();
    }

    public function test_missed_h_minus_one_is_recovered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 07:00:00', 'Asia/Jakarta'));

        [$order, $invoice] = $this->createDeliveredInvoice('missed-h-minus-one', '2026-09-01');
        $client = $this->useSuccessfulReminderClient();

        $this->artisan('invoices:reminders');

        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)->first();
        $this->assertSame(InvoiceReminder::EVENT_H_MINUS_ONE, $reminder->event_type);
        $this->assertCount(1, $client->idempotencyKeys);
        $this->assertSame($reminder->idempotency_key, $client->idempotencyKeys[0]);

        Carbon::setTestNow();
    }

    public function test_overdue_reminder_is_sent_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-11 07:00:00', 'Asia/Jakarta'));

        [$order, $invoice] = $this->createDeliveredInvoice('overdue-first', '2026-09-11');
        InvoiceReminder::create([
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'preexisting-h1-overdue-first',
        ]);
        $client = $this->useSuccessfulReminderClient();

        $this->artisan('invoices:reminders');
        $this->artisan('invoices:reminders');

        $this->assertSame(2, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $reminder = InvoiceReminder::where('invoice_id', $invoice->id)
            ->where('event_type', InvoiceReminder::EVENT_OVERDUE)
            ->first();
        $this->assertSame(InvoiceReminder::EVENT_OVERDUE, $reminder->event_type);
        $this->assertCount(1, $client->idempotencyKeys);
        $this->assertSame($reminder->idempotency_key, $client->idempotencyKeys[0]);

        Carbon::setTestNow();
    }

    public function test_asia_jakarta_pre_boundary_excludes_overdue_and_midnight_is_inclusive(): void
    {
        $client = $this->useSuccessfulReminderClient();
        [$order, $invoice] = $this->createDeliveredInvoice('jakarta-boundary', '2026-09-11');

        Carbon::setTestNow(Carbon::parse('2026-09-10 23:59:59', 'Asia/Jakarta'));
        $this->artisan('invoices:reminders');
        $this->assertDatabaseHas('invoice_reminders', [
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
        ]);
        $this->assertDatabaseMissing('invoice_reminders', [
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_OVERDUE,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 00:00:00', 'Asia/Jakarta'));
        $this->artisan('invoices:reminders');
        $this->assertDatabaseHas('invoice_reminders', [
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_OVERDUE,
        ]);
        $this->assertCount(2, $client->idempotencyKeys);

        Carbon::setTestNow();
    }

    public function test_scheduler_registers_invoice_reminders_in_jakarta(): void
    {
        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        $reminderEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'invoices:reminders'));
        $this->assertNotNull($reminderEvent, 'Invoice reminder scheduler event is not registered.');
        $this->assertSame('Asia/Jakarta', $reminderEvent->timezone);
    }
}
