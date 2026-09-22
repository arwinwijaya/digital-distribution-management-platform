<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver GPS breadcrumb for a delivery (Phase 8, T7).
 */
class StoreLocationPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() === true || $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
