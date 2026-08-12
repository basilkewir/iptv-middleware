<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_method' => ['required', 'in:stripe,paypal,crypto'],
            'token' => ['required_if:payment_method,stripe', 'nullable', 'string'],
            'subscription_id' => ['nullable', 'exists:subscriptions,id'],
        ];
    }
}
