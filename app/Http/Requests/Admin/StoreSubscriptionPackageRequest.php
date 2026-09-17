<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackageRenewalCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreSubscriptionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('subscription_packages', 'slug')],
            'renewal_cycle' => ['required', new Enum(PackageRenewalCycle::class)],
            'points_allocated' => ['required', 'integer', 'min:1'],
            'price_naira' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
