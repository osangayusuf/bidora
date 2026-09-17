<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->isPackageBased() ? 'package' : 'plan',
            'amount_naira' => (float) $this->amount_naira,
            'frequency' => $this->frequency?->value,
            'frequency_label' => $this->cycleLabel(),
            'package_name' => $this->subscriptionPackage?->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_cancellable' => $this->isCancellable(),
            'authorization_last4' => $this->authorization_last4,
            'authorization_brand' => $this->authorization_brand,
            'next_payment_date' => $this->next_payment_date?->toISOString(),
            'next_charge_at' => $this->next_charge_at?->toISOString(),
            'last_charged_at' => $this->last_charged_at?->toISOString(),
            'failure_count' => $this->failure_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
