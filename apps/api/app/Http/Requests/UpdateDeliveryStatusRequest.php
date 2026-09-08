<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'sales', 'driver'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:assigned,in_progress,delivered,failed'],
            'recipient_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proof_of_delivery_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'proof_of_delivery' => ['sometimes', 'nullable', 'array'],
            'proof_of_delivery.photo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'proof_of_delivery.signature_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'failure_reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
