<?php

namespace App\Http\Requests;

use App\Models\Outlet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:32',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            // Ownership is assigned by the server. Client supplied user_id is never accepted.
            'user_id' => 'prohibited',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $canonical = Outlet::canonicalizePhone((string) $this->input('phone'));
            if ($canonical === '' || Outlet::where('canonical_phone', $canonical)->exists()) {
                $validator->errors()->add('phone', 'The phone has already been taken or is invalid.');
            }
        });
    }
}
