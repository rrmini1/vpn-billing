<?php

namespace App\Services\Subscriptions;

use App\Models\MarzbanUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionActivator
{
    public function activate(User $user, Plan $plan, MarzbanService $marzban): Subscription
    {
        $existingMarzbanUser = MarzbanUser::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $marzbanUsername = null;

        if ($existingMarzbanUser) {
            $currentMarzbanUser = $marzban->getUser($existingMarzbanUser->username);
            $newDataLimitBytes = $this->calculateExtendedDataLimit(
                $currentMarzbanUser,
                (int) $plan->traffic_limit_bytes,
            );

            $marzbanResponse = $marzban->updateUserLimit(
                $existingMarzbanUser->username,
                $newDataLimitBytes,
            );
        } else {
            $marzbanUsername = $this->makeMarzbanUsername((int) $user->id, $plan->code);
            $marzbanResponse = $marzban->createUser(
                $marzbanUsername,
                (int) $plan->traffic_limit_bytes,
                'Checkout subscription for user #'.$user->id.' plan '.$plan->code,
            );
        }

        return DB::transaction(function () use ($user, $plan, $existingMarzbanUser, $marzbanResponse, $marzbanUsername): Subscription {
            $now = now();

            $user->subscriptions()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update([
                    'status' => Subscription::STATUS_REPLACED,
                    'ended_at' => $now,
                    'updated_at' => $now,
                ]);

            $subscription = $this->createSubscriptionSnapshot((int) $user->id, $plan, $now);

            if ($existingMarzbanUser) {
                $existingMarzbanUser->forceFill([
                    'subscription_id' => $subscription->id,
                    'status' => $marzbanResponse['status'] ?? MarzbanUser::STATUS_ACTIVE,
                    'data_limit_bytes' => $marzbanResponse['data_limit'] ?? $plan->traffic_limit_bytes,
                    'subscription_url' => $this->normalizeSubscriptionUrl(
                        $marzbanResponse['subscription_url'] ?? $existingMarzbanUser->getRawOriginal('subscription_url'),
                    ),
                    'raw_response' => $marzbanResponse,
                ])->save();

                return $subscription->load('marzbanUser');
            }

            MarzbanUser::query()->create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'username' => $marzbanResponse['username'] ?? $marzbanUsername,
                'status' => $marzbanResponse['status'] ?? MarzbanUser::STATUS_ACTIVE,
                'data_limit_bytes' => $marzbanResponse['data_limit'] ?? $plan->traffic_limit_bytes,
                'subscription_url' => $this->normalizeSubscriptionUrl($marzbanResponse['subscription_url'] ?? null),
                'raw_response' => $marzbanResponse,
            ]);

            return $subscription->load('marzbanUser');
        });
    }

    private function createSubscriptionSnapshot(int $userId, Plan $plan, ?Carbon $startedAt = null): Subscription
    {
        return Subscription::query()->create([
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'traffic_limit_bytes' => $plan->traffic_limit_bytes,
            'price_amount' => $plan->price_amount,
            'currency' => $plan->currency,
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => $startedAt ?? now(),
        ]);
    }

    private function makeMarzbanUsername(int $userId, string $planCode): string
    {
        return 'u'.$userId.'_'.$planCode.'_'.Str::lower(Str::random(8));
    }

    /**
     * @param  array<string, mixed>  $currentMarzbanUser
     */
    private function calculateExtendedDataLimit(array $currentMarzbanUser, int $planTrafficLimitBytes): int
    {
        $usedTrafficBytes = max(0, (int) ($currentMarzbanUser['used_traffic'] ?? 0));
        $currentDataLimitBytes = $currentMarzbanUser['data_limit'] ?? null;

        if (! is_numeric($currentDataLimitBytes)) {
            return $usedTrafficBytes + $planTrafficLimitBytes;
        }

        $remainingTrafficBytes = max(0, (int) $currentDataLimitBytes - $usedTrafficBytes);

        return $usedTrafficBytes + $remainingTrafficBytes + $planTrafficLimitBytes;
    }

    private function normalizeSubscriptionUrl(?string $subscriptionUrl): ?string
    {
        if (! $subscriptionUrl) {
            return null;
        }

        if (str_starts_with($subscriptionUrl, 'http://') || str_starts_with($subscriptionUrl, 'https://')) {
            return $subscriptionUrl;
        }

        return rtrim((string) config('marzban.base_url'), '/').'/'.ltrim($subscriptionUrl, '/');
    }
}
