<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'npwp' => ['sometimes', 'required', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer_text' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'signer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'signer_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'show_npwp' => ['sometimes', 'boolean'],
            'show_outlet_phone' => ['sometimes', 'boolean'],
            'logo' => ['sometimes', 'file', 'max:2048', 'mimes:jpg,jpeg,png,svg', 'mimetypes:image/jpeg,image/png,image/svg+xml'],
        ];
    }
}
