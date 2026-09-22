<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin-facing driver roster creation.
 *
 * `user_id` must reference an existing user whose role is `driver` and who does
 * not already own a roster profile. Ownership is never accepted from the client.
 */
class StoreDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:driver_profiles,user_id'],
            'vehicle_type' => ['nullable', 'string', 'max:50'],
            'plate_number' => ['nullable', 'string', 'max:20', 'unique:driver_profiles,plate_number'],
            'capacity_kg' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'service_territory_id' => ['nullable', 'integer', 'exists:territories,id'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'is_available' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('user_id')) {
                return;
            }

            $user = User::find($this->integer('user_id'));
            if (! $user || $user->role !== 'driver') {
                $validator->errors()->add('user_id', 'The selected user must have the driver role.');
            }
        });
    }
}
