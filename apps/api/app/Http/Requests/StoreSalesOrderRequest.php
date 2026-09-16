<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    /**
     * Only sales users may create sales orders.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->isSales();
    }

    /**
     * Derive a stable idempotency key from the outlet + canonical item payload
     * when the client does not supply one.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->filled('idempotency_key') && is_array($this->input('items'))) {
            $items = collect($this->input('items'))
                ->filter(fn ($item) => is_array($item))
                ->map(fn ($item) => [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity'   => (int) ($item['quantity'] ?? 0),
                ])
                ->sortBy('product_id')
                ->values()
                ->all();

            $outletId = $this->input('outlet_id') ?? 'unknown';
            $this->merge([
                'idempotency_key' => hash('sha256', json_encode([
                    'outlet_id' => $outletId,
                    'items'     => $items,
                ], JSON_THROW_ON_ERROR)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'outlet_id'      => 'required|integer|exists:outlets,id',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'promotion_id'   => 'nullable|integer|exists:promotions,id',
            // prepareForValidation supplies the payload-derived identity when the
            // client does not send one; it is never optional at the controller boundary.
            'idempotency_key' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'outlet_id.required' => 'An outlet is required to place a sales order.',
            'outlet_id.exists'   => 'The selected outlet does not exist.',
            'items.required'     => 'At least one item is required to place an order.',
            'items.min'          => 'At least one item is required to place an order.',
            'items.*.product_id.required' => 'Each item must reference a valid product.',
            'items.*.product_id.exists'   => 'The referenced product does not exist.',
            'items.*.product_id.distinct' => 'A product may only appear once in an order.',
            'items.*.quantity.required'   => 'Each item must have a quantity.',
            'items.*.quantity.min'        => 'Quantity must be at least 1.',
            'idempotency_key.required'    => 'A request identity is required for every order submission.',
        ];
    }
}
