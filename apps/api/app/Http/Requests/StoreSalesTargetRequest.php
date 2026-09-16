<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'       => 'required|integer|exists:users,id',
            'period'        => [
                'required',
                'string',
                'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                Rule::unique('sales_targets', 'period')->where(function ($query) {
                    return $query->where('user_id', $this->input('user_id'));
                }),
            ],
            'target_amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'period.regex'     => 'The period must be a valid Year-Month (YYYY-MM).',
            'period.unique'    => 'The sales target already exists for this user and period.',
            'target_amount.min'=> 'Target amount must not be negative.',
        ];
    }
}
