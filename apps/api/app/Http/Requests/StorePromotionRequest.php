<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:2000',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value'=> 'required|numeric|min:0',
            'max_discount'  => 'nullable|numeric|min:0',
            'product_id'    => 'nullable|integer|exists:products,id',
            'min_order'     => 'required|numeric|min:0',
            'start_date'    => 'required|date|before_or_equal:end_date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ];
    }
}
