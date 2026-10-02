<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class DummyModeService
{
    public const PDF_CACHE_KEY = 'dummy.invoice.pdf';

    public const DETAIL_CACHE_KEY = 'dummy.invoice.detail';

    public function __construct(private readonly PdfGeneratorService $pdfGenerator) {}

    /**
     * Check if dummy mode is enabled via config/app.dummy_mode.
     */
    public function enabled(): bool
    {
        return (bool) config('app.dummy_mode', false);
    }

    /**
     * Retrieve the cached deterministic detail payload, if present.
     *
     * @return array<string, mixed>|null
     */
    public function detail(): ?array
    {
        $detail = Cache::get(self::DETAIL_CACHE_KEY);

        return is_array($detail) ? $detail : null;
    }

    /**
     * Retrieve the pre-generated static PDF blob, if present.
     */
    public function pdfBlob(): ?string
    {
        $blob = Cache::get(self::PDF_CACHE_KEY);

        return is_string($blob) ? $blob : null;
    }

    /**
     * Build a response for the dummy PDF using the cached blob and the
     * canonical filename derived from the requested invoice.
     */
    public function pdfResponse(Invoice $invoice): Response
    {
        $blob = $this->pdfBlob();
        if ($blob === null) {
            abort(503, 'Dummy invoice PDF has not been generated.');
        }

        return response($blob, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfGenerator->filename($invoice).'"',
            'Content-Length' => strlen($blob),
        ]);
    }
}
