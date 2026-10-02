<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PdfGeneratorService
{
    /**
     * Generate and stream the invoice PDF with correct headers and filename.
     *
     * Uses server-side Dompdf only, read-only. Respects active template toggles
     * via the Blade view.
     */
    public function stream(Invoice $invoice, array $detail, InvoiceTemplate $template): Response
    {
        $isCancelled = $invoice->status === Invoice::CANCELLED;
        $isOverdue = $this->isOverdue($invoice);

        $datePart = $invoice->issue_date instanceof Carbon
            ? $invoice->issue_date->format('Ymd')
            : Carbon::parse($invoice->issue_date)->format('Ymd');

        // Fallback when issue_date is null
        if (empty($datePart) || $datePart === '19700101') {
            $datePart = Carbon::today()->format('Ymd');
        }

        $filename = 'INV-'.$datePart.'-'.$invoice->id.'.pdf';

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'detail' => $detail,
            'template' => $template,
            'isCancelled' => $isCancelled,
            'isOverdue' => $isOverdue,
        ]);

        // Dompdf options: disable remote fetching for performance / limit image impact
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setPaper('a4', 'portrait');

        // Use output + manual response to guarantee headers exactly as spec
        $content = $pdf->output();

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => strlen($content),
        ]);
    }

    /**
     * Convenience: render Blade HTML without Dompdf conversion (used in tests when PDF text extraction is flaky).
     */
    public function renderHtml(Invoice $invoice, array $detail, InvoiceTemplate $template): string
    {
        $isCancelled = $invoice->status === Invoice::CANCELLED;
        $isOverdue = $this->isOverdue($invoice);

        return view('invoices.pdf', [
            'invoice' => $invoice,
            'detail' => $detail,
            'template' => $template,
            'isCancelled' => $isCancelled,
            'isOverdue' => $isOverdue,
        ])->render();
    }

    private function isOverdue(Invoice $invoice): bool
    {
        if ($invoice->due_date === null) {
            return false;
        }
        $balance = (float) $invoice->balance_amount;
        if ($balance <= 0) {
            return false;
        }
        $dueDate = $invoice->due_date instanceof Carbon ? $invoice->due_date : Carbon::parse($invoice->due_date);
        return $dueDate->isPast() && $dueDate->toDateString() < Carbon::today()->toDateString();
    }
}
