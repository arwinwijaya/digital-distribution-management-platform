<?php

namespace App\Http\Controllers;

use App\Models\InvoiceReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceReminderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user?->role === 'admin';
        $isFinance = app(\App\Services\FinanceAuthorizationService::class)->isFinance($user ?? auth()->user());

        if (! $isAdmin && ! $isFinance) {
            $outlet = $user?->outlet;
            if (! $outlet) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized.',
                ], 403);
            }
        }

        $validator = Validator::make($request->query(), [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'outlet_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $page = (int) $request->query('page', 1);
        $limit = (int) $request->query('limit', 25);
        $outletId = $request->query('outlet_id') ? (int) $request->query('outlet_id') : null;

        $query = InvoiceReminder::query()
            ->with(['invoice:id,outlet_id,invoice_number,due_date,status'])
            ->orderBy('id');

        if ($isAdmin || $isFinance) {
            if ($outletId !== null) {
                $query->whereHas('invoice', fn ($q) => $q->where('outlet_id', $outletId));
            }
        } else {
            $outlet = $user->outlet;
            $query->whereHas('invoice', fn ($q) => $q->where('outlet_id', $outlet->id));
        }

        $total = (clone $query)->count();
        $rows = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;

        $data = $rows->take($limit)->values()->map(fn (InvoiceReminder $reminder) => [
            'id' => $reminder->id,
            'invoice_id' => $reminder->invoice_id,
            'outlet_id' => $reminder->invoice?->outlet_id,
            'event_type' => $reminder->event_type,
            'event_date' => $reminder->event_date?->toDateString(),
            'status' => $reminder->status,
            'attempts' => $reminder->attempts,
            'idempotency_key' => $reminder->idempotency_key,
            'next_attempt_at' => $reminder->next_attempt_at?->toIso8601String(),
            'sent_at' => $reminder->sent_at?->toIso8601String(),
            'failed_at' => $reminder->failed_at?->toIso8601String(),
            'created_at' => $reminder->created_at?->toIso8601String(),
            'updated_at' => $reminder->updated_at?->toIso8601String(),
            'invoice' => $reminder->invoice ? [
                'id' => $reminder->invoice->id,
                'invoice_number' => $reminder->invoice->invoice_number,
                'due_date' => $reminder->invoice->due_date?->toDateString(),
                'outlet_id' => $reminder->invoice->outlet_id,
                'status' => $reminder->invoice->status,
            ] : null,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
