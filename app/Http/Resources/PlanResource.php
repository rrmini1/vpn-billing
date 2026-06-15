<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'traffic_limit_bytes' => $this->traffic_limit_bytes,
            'price_amount' => $this->price_amount,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
