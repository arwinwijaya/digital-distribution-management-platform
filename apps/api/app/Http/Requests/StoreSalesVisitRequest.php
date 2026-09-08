<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSalesVisitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'visit_date' => $this->input('visit_date', $this->input('date', $this->input('scheduled_date'))),
            'target' => $this->input('target', $this->input('target_name')),
        ]);
    }

    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'sales'], true);
    }

    public function rules(): array
    {
        return [
            'sales_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'outlet_id' => ['sometimes', 'nullable', 'integer', 'exists:outlets,id'],
            'target_id' => ['sometimes', 'nullable', 'integer'],
            'target' => ['sometimes', 'nullable', 'string', 'max:255'],
            'visit_date' => ['required', 'date_format:Y-m-d'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:planned,completed,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->filled('target') && !$this->filled('target_id') && !$this->filled('outlet_id')) {
                $validator->errors()->add('target', 'A target, target_id, or outlet_id is required.');
            }
        });
    }
}
