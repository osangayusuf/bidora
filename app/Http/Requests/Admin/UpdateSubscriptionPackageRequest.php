<?php

namespace App\Http\Requests\Admin;

use App\Enums\PackageRenewalCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPackageRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('subscription_packages', 'slug')->ignore($this->route('package'))],
            'renewal_cycle' => ['required', Rule::in(array_map(fn (PackageRenewalCycle $cycle) => $cycle->value, PackageRenewalCycle::sellable()))],
            'points_allocated' => ['required', 'integer', 'min:1'],
            'price_naira' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
