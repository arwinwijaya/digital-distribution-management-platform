<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Services\DummyModeService;
use App\Services\FinanceAuthorizationService;
use App\Services\InvoiceService;
use App\Services\PdfGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly FinanceAuthorizationService $authorization,
        private readonly PdfGeneratorService $pdfGenerator,
        private readonly DummyModeService $dummyMode,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->authorizedInvoice($request, $id);

        // Dummy mode: serve the deterministic pre-seeded payload so offline
        // and E2E runs never touch the live query path. Falls back to the real
        // service when no dummy payload has been seeded.
        $detail = $this->dummyMode->enabled()
            ? ($this->dummyMode->detail() ?? $this->invoiceService->getDetail($invoice))
            : $this->invoiceService->getDetail($invoice);

        return response()->json([
            'status' => 'success',
            'data' => $detail,
        ]);
    }

    /**
     * Stream the invoice as a server-side PDF. Reuses the detail service so the
     * PDF and JSON never diverge, and applies the exact same authorization and
     * outlet-ownership rules as `show`.
     *
     * In dummy mode the pre-generated static blob is returned as-is — Dompdf is
     * never invoked, keeping the request zero-network and deterministic.
     */
    public function pdf(Request $request, int $id): Response
    {
        $invoice = $this->authorizedInvoice($request, $id);

        if ($this->dummyMode->enabled()) {
            return $this->dummyMode->pdfResponse($invoice);
        }

        $detail = $this->invoiceService->getDetail($invoice);
        $template = InvoiceTemplate::query()->orderBy('id')->firstOrFail();

        return $this->pdfGenerator->stream($invoice, $detail, $template);
    }

    /**
     * Resolve the invoice and enforce RBAC + outlet ownership identically for
     * the detail and PDF endpoints. Aborts 403/404 via exceptions so both
     * surfaces share one contract.
     */
    private function authorizedInvoice(Request $request, int $id): Invoice
    {
        $user = $request->user();
        $isAdmin = $this->authorization->isAdmin($user);
        $isFinance = $this->authorization->isFinance($user);
        $isOutlet = $this->authorization->hasCurrentRole($user, 'outlet');

        if (! $isAdmin && ! $isFinance && ! $isOutlet) {
            abort(403, 'Unauthorized.');
        }

        // Find the header first; do not load sensitive relations until ownership is verified.
        $invoice = Invoice::query()->findOrFail($id);

        if (! $isAdmin && ! $isFinance) {
            $outlet = $user->outlet;
            if (! $outlet) {
                abort(403, 'The authenticated user is not associated with an outlet.');
            }
            if ($invoice->outlet_id !== $outlet->id) {
                abort(403, 'Forbidden.');
            }
        }

        return $invoice;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->authorization->isAdmin($user);
        $isFinance = $this->authorization->isFinance($user);
        if (! $isAdmin && ! $isFinance && ! $this->authorization->hasCurrentRole($user, 'outlet')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 25);
        $query = Invoice::query()->orderByDesc('id');

        if (! $isAdmin && ! $isFinance) {
            $outlet = $user->outlet;
            if (! $outlet) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The authenticated user is not associated with an outlet.',
                ], 403);
            }
            $query->where('outlet_id', $outlet->id);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'status' => 'success',
            'data' => $rows->take($limit)->values()->map(fn (Invoice $invoice) => $this->invoiceService->format($invoice)),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
