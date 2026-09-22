<?php

namespace App\Http\Requests;

use App\Models\DriverProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-facing driver roster update (PATCH). All fields optional; a supplied
 * `plate_number` must stay unique across the roster (excluding the current row).
 */
class UpdateDriverProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = $this->route('id');
        $current = $profileId ? DriverProfile::find($profileId) : null;

        return [
            'vehicle_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'plate_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('driver_profiles', 'plate_number')->ignore($current?->id),
            ],
            'capacity_kg' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'service_territory_id' => ['sometimes', 'nullable', 'integer', 'exists:territories,id'],
            'shift_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'shift_end' => ['sometimes', 'nullable', 'date_format:H:i'],
            'is_available' => ['sometimes', 'boolean'],
            'user_id' => ['prohibited'],
        ];
    }
}
