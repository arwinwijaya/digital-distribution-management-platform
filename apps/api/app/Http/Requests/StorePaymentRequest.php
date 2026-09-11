<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Services\FinanceAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && (
            app(FinanceAuthorizationService::class)->isAdmin($user)
            || app(FinanceAuthorizationService::class)->isFinance($user)
            || app(FinanceAuthorizationService::class)->hasCurrentRole($user, 'outlet')
        );
    }

    protected function prepareForValidation(): void
    {
        // Accept the public server-generated order reference as well as the
        // internal numeric key returned by the order API.
        $reference = $this->input('order_id');
        if (is_string($reference) && !ctype_digit($reference)) {
            $order = Order::where('order_id', $reference)->first();
            if ($order) {
                $this->merge(['order_id' => $order->id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,bank,transfer,other'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
