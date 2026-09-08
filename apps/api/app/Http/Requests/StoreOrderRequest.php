<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && $user->isOutlet();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        // A request identity is required for every submission. If the client does
        // not provide one, derive a stable identity from the authenticated outlet
        // and canonical item payload so retries without a header are idempotent.
        if (!$this->filled('idempotency_key') && is_array($this->input('items'))) {
            $items = collect($this->input('items'))
                ->filter(fn ($item) => is_array($item))
                ->map(fn ($item) => [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ])
                ->sortBy('product_id')
                ->values()
                ->all();

            $outletId = $this->user()?->outlet?->getKey() ?? 'unknown';
            $this->merge([
                'idempotency_key' => hash('sha256', json_encode([
                    'outlet_id' => $outletId,
                    'items' => $items,
                ], JSON_THROW_ON_ERROR)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            // prepareForValidation supplies the payload-derived identity when the
            // client does not send one; it is never optional at the controller boundary.
            'idempotency_key' => 'required|string|max:255',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required to place an order.',
            'items.min' => 'At least one item is required to place an order.',
            'items.*.product_id.required' => 'Each item must reference a valid product.',
            'items.*.product_id.exists' => 'The referenced product does not exist.',
            'items.*.product_id.distinct' => 'A product may only appear once in an order.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'idempotency_key.required' => 'A request identity is required for every order submission.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
