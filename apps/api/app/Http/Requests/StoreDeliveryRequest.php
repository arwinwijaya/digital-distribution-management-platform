<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'sales'], true);
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (!$this->filled('driver_id')) {
                return;
            }
            $driver = User::find($this->integer('driver_id'));
            if (!$driver || $driver->role !== 'driver' || !$driver->is_active) {
                $validator->errors()->add('driver_id', 'The selected user must be an active driver.');
            }
        });
    }
}
