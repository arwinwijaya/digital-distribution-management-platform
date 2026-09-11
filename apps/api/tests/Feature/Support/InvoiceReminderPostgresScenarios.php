<?php

namespace Tests\Feature\Support;

use App\Models\Invoice;
use App\Models\InvoiceReminder;
use App\Models\Order;
use Illuminate\Support\Carbon;

trait InvoiceReminderPostgresScenarios
{
    public function test_concurrent_invoice_reminder_workers(): void
    {
        if (config('database.default') !== 'pgsql' || ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('PostgreSQL concurrency coverage requires DB_CONNECTION=pgsql and pdo_pgsql.');
        }

        $orderIdentity = hash('sha256', $this->prefix.'-reminder-order');
        $order = Order::create([
            'order_id' => 'ORD-'.$this->prefix,
            'outlet_id' => $this->outlet->id,
            'status' => 'Delivered',
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => $orderIdentity,
        ]);
        $this->orderIdentity = $orderIdentity;
        $invoice = Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $this->outlet->id,
            'invoice_number' => 'INV-'.$this->prefix,
            'issue_date' => Carbon::now('Asia/Jakarta')->subDays(10)->toDateString(),
            'due_date' => Carbon::now('Asia/Jakarta')->addDay()->toDateString(),
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'balance_amount' => 1000000,
            'status' => Invoice::UNPAID,
        ]);

        $this->startProviderServer();
        $this->startCommandWorkers();
        foreach ($this->commandProcesses as $process) {
            proc_close($process);
        }
        $this->commandProcesses = [];

        $reminder = InvoiceReminder::query()->where('invoice_id', $invoice->id)->sole();
        $this->assertSame(InvoiceReminder::SENT, $reminder->status);
        $this->assertSame(1, InvoiceReminder::where('invoice_id', $invoice->id)->count());
        $providerCalls = array_values(array_filter(
            file($this->providerCallFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
            fn (string $key): bool => $key === $reminder->idempotency_key,
        ));
        $this->assertCount(1, $providerCalls, 'Concurrent workers must produce one keyed provider call.');
    }
}
