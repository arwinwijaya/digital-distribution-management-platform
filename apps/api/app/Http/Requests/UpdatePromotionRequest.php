<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'sometimes|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value'=> 'sometimes|numeric|min:0',
            'max_discount'  => 'nullable|numeric|min:0',
            'product_id'    => 'nullable|integer|exists:products,id',
            'min_order'     => 'sometimes|numeric|min:0',
            'start_date'    => 'sometimes|date|before_or_equal:end_date',
            'end_date'      => 'sometimes|date|after_or_equal:start_date',
            'is_active'     => 'sometimes|boolean',
        ];
    }
}
