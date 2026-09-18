<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CanonicalizesOutletPhone;
use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-facing outlet creation.
 *
 * Distinct from the public `StoreOutletRequest` (self-registration) because the
 * admin path accepts category / territory / is_active and must NOT leak those
 * fields onto the public route.
 */
class StoreAdminOutletRequest extends FormRequest
{
    use CanonicalizesOutletPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:32',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'category' => ['required', 'string', Rule::in(Outlet::VALID_CATEGORIES)],
            'territory_id' => 'nullable|integer|exists:territories,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
            // Ownership is server-assigned; the client may never inject it.
            'user_id' => 'prohibited',
        ];
    }
}
