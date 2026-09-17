<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'renewal_cycle' => $this->renewal_cycle->value,
            'renewal_cycle_label' => $this->renewal_cycle->label(),
            'points_allocated' => (int) $this->points_allocated,
            'price_naira' => (float) $this->price_naira,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
