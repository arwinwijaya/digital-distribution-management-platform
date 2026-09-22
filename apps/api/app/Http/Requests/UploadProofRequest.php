<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Proof-of-delivery media upload (Phase 8, T8).
 *
 * Photo is the delivery photo (jpeg/png/webp, max 5 MB); signature is the
 * captured canvas export (png, max 2 MB). Coordinates are optional but, when
 * present, are bounded.
 */
class UploadProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'driver'], true);
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
