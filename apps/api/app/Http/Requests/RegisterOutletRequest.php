<?php

namespace App\Http\Requests;

use App\Models\Outlet;
use Illuminate\Foundation\Http\FormRequest;

class RegisterOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:32',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
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
