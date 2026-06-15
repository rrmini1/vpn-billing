<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Payments\PaymentActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = $request->user()
            ->payments()
            ->latest('id')
            ->get();

        return PaymentResource::collection($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
        ]);

        $plan = Plan::query()
            ->active()
            ->where('code', $attributes['plan_code'])
            ->whereNot('code', 'trial')
            ->first();

        if (! $plan) {
            return response()->json([
                'message' => 'Selected plan is not available for payment.',
            ], 422);
        }

        $payment = Payment::query()->create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'traffic_limit_bytes' => $plan->traffic_limit_bytes,
            'amount' => $plan->price_amount,
            'currency' => $plan->currency,
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
            'provider_payment_id' => 'mock_'.Str::uuid(),
        ]);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function simulatePaid(
        Request $request,
        Payment $payment,
        PaymentActivationService $activation,
    ): JsonResponse {
        if ($payment->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            return response()->json([
                'message' => 'Payment is not pending.',
            ], 409);
        }

        if (! $payment->plan) {
            return response()->json([
                'message' => 'Payment plan is no longer available.',
            ], 422);
        }

        $activation->markPaidAndDispatch($payment);

        return response()->json([
            'payment' => new PaymentResource($payment->refresh()),
        ], 202);
    }
}
