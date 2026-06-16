<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'activation_status' => $this->activation_status,
            'activation_error' => $this->activation_error,
            'provider' => $this->provider,
            'provider_payment_id' => $this->provider_payment_id,
            'confirmation_url' => $this->confirmation_url,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'plan' => [
                'id' => $this->plan_id,
                'code' => $this->plan_code,
                'name' => $this->plan_name,
                'traffic_limit_bytes' => $this->traffic_limit_bytes,
            ],
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'subscription_id' => $this->subscription_id,
            'expires_at' => $this->expires_at?->toISOString(),
            'activated_at' => $this->activated_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
