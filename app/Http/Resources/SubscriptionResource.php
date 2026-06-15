<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'status' => $this->status,
            'plan' => [
                'id' => $this->plan_id,
                'code' => $this->plan_code,
                'name' => $this->plan_name,
                'traffic_limit_bytes' => $this->traffic_limit_bytes,
                'price_amount' => $this->price_amount,
                'currency' => $this->currency,
            ],
            'marzban_user' => $this->whenLoaded('marzbanUser', fn (): array => [
                'username' => $this->marzbanUser->username,
                'status' => $this->marzbanUser->status,
                'data_limit_bytes' => $this->marzbanUser->data_limit_bytes,
                'subscription_url' => $this->marzbanUser->subscription_url,
            ]),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
