<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopUpRequest extends FormRequest
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
        $min = (float) config('points.min_deposit_naira');
        $max = (float) config('points.max_deposit_naira');

        return [
            'amount' => ['required', 'numeric', "min:{$min}", "max:{$max}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum top-up is ₦'.number_format((float) config('points.min_deposit_naira')),
            'amount.max' => 'The maximum top-up is ₦'.number_format((float) config('points.max_deposit_naira')),
        ];
    }
}
