<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaystackPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_naira' => $this->amountNaira(),
            'frequency' => $this->interval->value,
            'frequency_label' => $this->interval->label(),
        ];
    }
}
