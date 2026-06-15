<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\PaymentActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentWebhookController extends Controller
{
    public function handleMock(Request $request, PaymentActivationService $activation): JsonResponse
    {
        $attributes = $request->validate([
            'event' => ['required', 'string'],
            'provider_payment_id' => ['required', 'string'],
        ]);

        if ($attributes['event'] !== 'payment.succeeded') {
            return response()->json([
                'message' => 'Webhook event ignored.',
            ], 202);
        }

        $payment = Payment::query()
            ->where('provider', 'mock')
            ->where('provider_payment_id', $attributes['provider_payment_id'])
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'provider_payment_id' => 'Payment was not found.',
            ]);
        }

        $dispatched = $activation->markPaidAndDispatch($payment);

        return response()->json([
            'payment' => new PaymentResource($payment->refresh()),
            'dispatched' => $dispatched,
        ]);
    }
}
