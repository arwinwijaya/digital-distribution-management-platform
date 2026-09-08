<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('limit_amount')) {
            $value = $this->input('credit_limit', $this->input('amount'));
            if ($value !== null) {
                $this->merge(['limit_amount' => $value]);
            }
        }
    }

    public function rules(): array
    {
        return ['limit_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2']];
    }
}
