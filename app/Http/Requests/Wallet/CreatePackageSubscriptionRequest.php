<?php

namespace App\Http\Requests\Wallet;

use App\Models\SubscriptionPackage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePackageSubscriptionRequest extends FormRequest
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
            'subscription_package_id' => [
                'required',
                Rule::exists(SubscriptionPackage::class, 'id')->where('is_active', true),
            ],
        ];
    }

    public function package(): SubscriptionPackage
    {
        return SubscriptionPackage::findOrFail($this->validated('subscription_package_id'));
    }
}
