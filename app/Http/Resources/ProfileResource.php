<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
                'locale' => $this->locale,
                'email_verified' => $this->hasVerifiedEmail(),
                'email_verified_at' => $this->email_verified_at?->toISOString(),
                'telegram' => [
                    'linked' => $this->telegram_id !== null,
                    'id' => $this->telegram_id,
                    'username' => $this->telegram_username,
                    'first_name' => $this->telegram_first_name,
                    'last_name' => $this->telegram_last_name,
                    'photo_url' => $this->telegram_photo_url,
                ],
                'created_at' => $this->created_at?->toISOString(),
            ],
            'current_subscription' => $this->whenLoaded('subscriptions', function (): ?SubscriptionResource {
                $subscription = $this->subscriptions
                    ->firstWhere('status', Subscription::STATUS_ACTIVE);

                return $subscription ? new SubscriptionResource($subscription) : null;
            }),
        ];
    }
}
