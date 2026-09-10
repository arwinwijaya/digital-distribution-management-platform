<?php

namespace App\Http\Requests;

use App\Services\FinanceAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class SetPaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(FinanceAuthorizationService::class)->isAdmin($user);
    }

    public function rules(): array
    {
        return [
            'payment_term_days' => ['bail', 'required', 'integer', 'min:1', 'max:90'],
        ];
    }
}
