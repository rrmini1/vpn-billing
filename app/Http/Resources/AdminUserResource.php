<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->whenLoaded('subscriptions', function (): ?Subscription {
            return $this->subscriptions->first();
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->displayEmail(),
            'role' => $this->role,
            'email_verified' => $this->hasVerifiedEmail(),
            'telegram' => [
                'linked' => $this->telegram_id !== null,
                'id' => $this->telegram_id,
                'username' => $this->telegram_username,
            ],
            'current_subscription' => $subscription instanceof Subscription
                ? new SubscriptionResource($subscription)
                : null,
            'payments_count' => $this->whenCounted('payments'),
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
