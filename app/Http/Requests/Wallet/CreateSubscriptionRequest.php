<?php

namespace App\Http\Requests\Wallet;

use App\Models\PaystackPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'paystack_plan_id' => ['required', Rule::exists(PaystackPlan::class, 'id')],
        ];
    }

    public function plan(): PaystackPlan
    {
        return PaystackPlan::findOrFail($this->validated('paystack_plan_id'));
    }
}
