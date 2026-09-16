<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /auth/me.
 * The `role` field is intentionally NOT accepted here;
 * role assignment goes through UserRoleController::assignRole.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (bool) $user->is_active;
    }

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                'unique:users,email,' . $this->user()?->getKey(),
            ],
            // `role` intentionally omitted — must not be accepted via profile update.
        ];
    }
}
