<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\FinanceAuthorizationService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly FinanceAuthorizationService $authorization,
    ) {}

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
