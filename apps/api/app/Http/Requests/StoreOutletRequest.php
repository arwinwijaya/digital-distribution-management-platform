<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\CanonicalizesOutletPhone;
use App\Services\FinanceAuthorizationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    use CanonicalizesOutletPhone;

    /**
     * Self-service outlet registration is open to every authenticated role
     * EXCEPT finance (K-C: the `outlets` menu level cannot express this — the
     * registering outlet role holds only `read` on `outlets`, and finance also
     * holds `read`). Denying finance here runs before validation so an empty
     * payload still yields 403 for finance rather than 422.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! app(FinanceAuthorizationService::class)->isFinance($user);
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
}
