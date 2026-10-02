<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    /**
     * Hard cap on an inlined logo file (bytes). Anything larger is skipped so a
     * pathological upload can never blow past the 5s PDF budget.
     */
    public const MAX_LOGO_BYTES = 2 * 1024 * 1024;

    /**
     * Generate and stream the invoice PDF with correct headers and filename.
     *
     * Uses server-side Dompdf only, read-only. Respects active template toggles
     * via the Blade view.
     */
    public function stream(Invoice $invoice, array $detail, InvoiceTemplate $template): Response
    {
        $content = $this->generate($invoice, $detail, $template);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->filename($invoice).'"',
            'Content-Length' => strlen($content),
        ]);
    }

    /**
     * Render the invoice PDF and return the raw bytes. Split out from stream()
     * so dummy-mode seeding can pre-generate the static blob once without
     * duplicating the view/option wiring.
     */
    public function generate(Invoice $invoice, array $detail, InvoiceTemplate $template): string
    {
        $isCancelled = $invoice->status === Invoice::CANCELLED;
        $isOverdue = $this->isOverdue($invoice);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'detail' => $detail,
            'template' => $template,
            'isCancelled' => $isCancelled,
            'isOverdue' => $isOverdue,
            'logoDataUri' => $this->resolveLogoDataUri($template),
        ]);

        // Dompdf options: disable remote fetching for performance / limit image impact.
        $pdf->setOption('isRemoteEnabled', false);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setPaper('a4', 'portrait');

        // `compress: 0` keeps the content stream uncompressed so template text
        // (company name, colors, NPWP, watermark) is byte-searchable in tests.
        return $pdf->output(['compress' => 0]);
    }

    /**
     * Canonical download filename shared by real and dummy modes so the two
     * surfaces can never drift: INV-YYYYMMDD-{id}.pdf.
     */
    public function filename(Invoice $invoice): string
    {
        $datePart = $invoice->issue_date instanceof Carbon
            ? $invoice->issue_date->format('Ymd')
            : Carbon::parse($invoice->issue_date)->format('Ymd');

        // Fallback when issue_date is null.
        if (empty($datePart) || $datePart === '19700101') {
            $datePart = Carbon::today()->format('Ymd');
        }

        return 'INV-'.$datePart.'-'.$invoice->id.'.pdf';
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
            'logoDataUri' => $this->resolveLogoDataUri($template),
        ])->render();
    }

    /**
     * Resolve the template logo into an inline base64 data URI so Dompdf never
     * performs a network round-trip. Oversized or missing files are skipped
     * (returns null) to bound rendering cost.
     */
    private function resolveLogoDataUri(InvoiceTemplate $template): ?string
    {
        $path = $template->logo_path;
        if (empty($path)) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        try {
            if ($disk->size($path) > self::MAX_LOGO_BYTES) {
                return null;
            }
            $contents = $disk->get($path);
        } catch (\Throwable) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
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
