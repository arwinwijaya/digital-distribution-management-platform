<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCreditLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return ['limit_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2']];
    }
}
