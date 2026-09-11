<?php

namespace Tests\Feature;

use App\Models\InvoiceReminder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Support\InvoiceReminderFeatureCase;

class InvoiceReminderHistoryTest extends InvoiceReminderFeatureCase
{
    public function test_reminder_history_is_bounded_and_scoped(): void
    {
        [$orderA, $invoiceA] = $this->createDeliveredInvoice('history-a', '2026-09-11');
        [$orderB, $invoiceB] = $this->createDeliveredInvoice('history-b', '2026-09-11');

        InvoiceReminder::create([
            'invoice_id' => $invoiceA->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'reminder-history-a',
        ]);
        InvoiceReminder::create([
            'invoice_id' => $invoiceB->id,
            'event_type' => InvoiceReminder::EVENT_OVERDUE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'reminder-history-b',
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson('/api/finance/reminders?outlet_id='.$this->outlet->id.'&page=1&limit=1');

        $response->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_id', $invoiceA->id);
    }

    public function test_invalid_reminder_pagination_is_rejected(): void
    {
        $this->withToken($this->adminToken)
            ->getJson('/api/finance/reminders?page=0&limit=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page', 'limit']);
    }

    public function test_reminder_history_requires_current_active_authorization(): void
    {
        [$order, $invoice] = $this->createDeliveredInvoice('authorization', '2026-09-11');
        InvoiceReminder::create([
            'invoice_id' => $invoice->id,
            'event_type' => InvoiceReminder::EVENT_H_MINUS_ONE,
            'event_date' => '2026-09-11',
            'status' => InvoiceReminder::SENT,
            'idempotency_key' => 'reminder-authorization',
        ]);

        $outletUser = User::factory()->outlet()->create();
        $this->outlet->update(['user_id' => $outletUser->id]);
        $token = auth()->login($outletUser);
        $this->withToken($token)->getJson('/api/finance/reminders')->assertOk();

        // The old token and outlet relation must not preserve access after role removal.
        $outletUser->update(['role' => 'sales']);
        $this->withToken($token)->getJson('/api/finance/reminders')->assertForbidden();

        // Raw role alone must not grant an inactive administrator access.
        $this->admin->update(['is_active' => false]);
        $this->withToken($this->adminToken)->getJson('/api/finance/reminders')->assertForbidden();
    }
}
