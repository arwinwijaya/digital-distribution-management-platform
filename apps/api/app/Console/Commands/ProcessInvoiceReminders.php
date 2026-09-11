<?php

namespace App\Console\Commands;

use App\Services\InvoiceReminderService;
use Illuminate\Console\Command;

class ProcessInvoiceReminders extends Command
{
    protected $signature = 'invoices:reminders';

    protected $description = 'Create and send idempotent WhatsApp invoice reminders with bounded retries';

    public function handle(InvoiceReminderService $service): int
    {
        $summary = $service->process();

        $this->line("Created: {$summary['created']}");
        $this->line("Retries: {$summary['retries']}");
        $this->line("Skipped: {$summary['skipped']}");
        $this->line("Sent: {$summary['sent']}");
        $this->line("Failed: {$summary['failed']}");

        return self::SUCCESS;
    }
}
