<?php

namespace App\Http\Requests;

use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    /**
     * Role assignment is platform_owner-only. Uses UserPolicy::assignRole
     * to keep the “admin denied / platform_owner allowed” rule centralized.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(UserPolicy::class)->assignRole($user);
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                'in:admin,supplier,outlet,sales,driver,finance,platform_owner',
            ],
        ];
    }
}
