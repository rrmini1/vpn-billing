<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\MarzbanUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Marzban\MarzbanService;
use App\Services\Subscriptions\SubscriptionActivator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function current(Request $request): JsonResource|JsonResponse
    {
        $subscription = $request->user()
            ->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('marzbanUser')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return response()->json([
                'data' => null,
            ]);
        }

        return new SubscriptionResource($subscription);
    }

    public function trial(Request $request, MarzbanService $marzban): JsonResource|JsonResponse
    {
        $user = $request->user();

        if ($user->subscriptions()->where('plan_code', 'trial')->exists()) {
            return response()->json([
                'message' => 'Trial subscription has already been issued.',
            ], 409);
        }

        $plan = Plan::query()
            ->active()
            ->where('code', 'trial')
            ->firstOrFail();

        $marzbanUsername = $this->makeMarzbanUsername((int) $user->id);

        try {
            $marzbanResponse = $marzban->createUser(
                $marzbanUsername,
                (int) $plan->traffic_limit_bytes,
                'Trial subscription for user #'.$user->id,
            );
        } catch (MarzbanApiException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not create Marzban user.',
            ], 502);
        }

        $subscription = DB::transaction(function () use ($user, $plan, $marzbanUsername, $marzbanResponse): Subscription {
            $subscription = $this->createSubscriptionSnapshot((int) $user->id, $plan);

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

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    public function checkout(Request $request, MarzbanService $marzban, SubscriptionActivator $activator): JsonResource|JsonResponse
    {
        $attributes = $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
        ]);

        $user = $request->user();
        $plan = Plan::query()
            ->active()
            ->where('code', $attributes['plan_code'])
            ->whereNot('code', 'trial')
            ->first();

        if (! $plan) {
            return response()->json([
                'message' => 'Selected plan is not available for checkout.',
            ], 422);
        }

        try {
            $subscription = $activator->activate($user, $plan, $marzban);
        } catch (MarzbanApiException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not activate subscription in Marzban.',
            ], 502);
        }

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
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

    private function makeMarzbanUsername(int $userId, string $planCode = 'trial'): string
    {
        return 'u'.$userId.'_'.$planCode.'_'.Str::lower(Str::random(8));
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
