<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecommendationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the `rbac:ai_actions:edit` middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'type'              => 'required|string|in:draft_order,draft_campaign',
            'outlet_id'         => 'required|integer|exists:outlets,id',
            'items'             => 'required|array|min:1',
            'items.*.product_id'=> 'required|integer|exists:products,id',
            'items.*.quantity'  => 'required|integer|min:1',
            'idempotency_key'   => 'required|string|max:128',
            'source_event_id'   => 'nullable|integer|exists:recommendation_events,id',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'                => 'The action type is required.',
            'type.in'                      => 'The type must be one of: draft_order, draft_campaign.',
            'outlet_id.required'           => 'The outlet_id is required.',
            'outlet_id.exists'             => 'The selected outlet does not exist.',
            'items.required'               => 'The items array is required.',
            'items.min'                    => 'At least one item is required.',
            'items.*.product_id.required'  => 'Each item must have a product_id.',
            'items.*.product_id.exists'    => 'One or more products do not exist.',
            'items.*.quantity.required'    => 'Each item must have a quantity.',
            'items.*.quantity.min'         => 'Quantity must be at least 1.',
            'idempotency_key.required'     => 'An idempotency key is required.',
            'idempotency_key.max'          => 'The idempotency key must not exceed 128 characters.',
            'source_event_id.exists'       => 'The referenced recommendation event does not exist.',
        ];
    }
}