<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceBackfillService;
use Illuminate\Console\Command;
use Throwable;

class BackfillInvoices extends Command
{
    protected $signature = 'invoices:backfill {--chunk=100 : Number of orders to process per batch}';

    protected $description = 'Create and reconcile invoices for eligible legacy orders';

    public function handle(InvoiceBackfillService $backfill): int
    {
        $chunkSize = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunkSize === false || $chunkSize < 1) {
            $this->error('The --chunk option must be a positive integer.');

            return self::FAILURE;
        }

        $summary = $backfill->backfill($chunkSize, function (
            string $event,
            Order $order,
            ?Invoice $invoice,
            ?Throwable $exception,
        ): void {
            $label = $order->order_id.' (id '.$order->id.')';

            match ($event) {
                'created' => $this->line("Created invoice for {$label}: {$invoice?->invoice_number}"),
                'reused' => $this->line("Reused invoice for {$label}: {$invoice?->invoice_number}"),
                'skipped' => $this->line("Skipped {$label}: status {$order->status}"),
                'failed' => $this->error("Failed {$label}: ".($exception?->getMessage() ?? 'unknown error')),
                default => null,
            };
        });

        $this->line("Created: {$summary['created']}");
        $this->line("Reused: {$summary['reused']}");
        $this->line("Skipped: {$summary['skipped']}");
        $this->line("Failed: {$summary['failed']}");

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
