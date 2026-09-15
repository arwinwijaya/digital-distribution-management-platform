<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListOperationalIssuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source' => ['sometimes', 'string', 'in:whatsapp,pipeline,reminder,operational_event'],
            'status' => ['sometimes', 'string', 'in:failed,sent,pending,completed,warning,info,processed'],
            'severity' => ['sometimes', 'string', 'in:critical,warning,info'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'correlation_id' => ['sometimes', 'string', 'max:100', 'regex:/\A[A-Za-z0-9._:\-]{1,100}\z/'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();
        if (! isset($data['page'])) {
            $data['page'] = 1;
        }
        if (! isset($data['limit'])) {
            $data['limit'] = 25;
        }
        $data['page'] = (int) $data['page'];
        $data['limit'] = (int) $data['limit'];

        return $data;
    }
}
