<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'store' => [
                'plan_id' => ['required', 'exists:subscription_plans,id'],
                'payment_method' => ['required', 'in:stripe,paypal,crypto'],
            ],
            'cancel' => [
                'reason' => ['nullable', 'string', 'max:500'],
            ],
            'upgrade' => [
                'new_plan_id' => ['required', 'exists:subscription_plans,id'],
            ],
            default => [],
        };
    }
}
