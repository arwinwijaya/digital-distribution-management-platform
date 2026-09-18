<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CanonicalizesOutletPhone;
use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletRequest extends FormRequest
{
    use CanonicalizesOutletPhone;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            // `required` (not just `sometimes`) so an explicit empty string is a
            // 422 rather than a model-level InvalidArgumentException (500).
            'phone' => 'sometimes|required|string|max:32',
            'category' => ['sometimes', 'required', 'string', Rule::in(Outlet::VALID_CATEGORIES)],
            'territory_id' => 'sometimes|nullable|integer|exists:territories,id',
            'address' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'district' => 'sometimes|required|string|max:255',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
