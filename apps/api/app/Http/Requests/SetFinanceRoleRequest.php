<?php

namespace App\Http\Requests;

use App\Services\FinanceAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class SetFinanceRoleRequest extends FormRequest
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
            'role' => ['sometimes', 'string', 'in:finance'],
        ];
    }
}
