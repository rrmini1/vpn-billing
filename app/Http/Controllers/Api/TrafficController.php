<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrafficResource;
use App\Models\Subscription;
use App\Services\Marzban\MarzbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrafficController extends Controller
{
    public function show(Request $request, MarzbanService $marzban): TrafficResource|JsonResponse
    {
        $subscription = $request->user()
            ->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('marzbanUser')
            ->latest('id')
            ->first();

        if (! $subscription?->marzbanUser) {
            return response()->json([
                'data' => null,
            ]);
        }

        try {
            $marzbanUser = $marzban->getUser($subscription->marzbanUser->username);
        } catch (MarzbanApiException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Could not fetch traffic from Marzban.',
            ], 502);
        }

        return new TrafficResource($marzbanUser);
    }
}
