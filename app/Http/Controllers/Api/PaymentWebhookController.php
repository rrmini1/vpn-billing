<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Payments\PaymentActivationService;
use App\Services\Payments\YooKassaPaymentProvider;
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

    public function handleYooKassa(
        Request $request,
        PaymentActivationService $activation,
        YooKassaPaymentProvider $provider,
    ): JsonResponse {
        $attributes = $request->validate([
            'event' => ['required', 'string'],
            'object.id' => ['required', 'string'],
        ]);

        $providerPaymentId = data_get($attributes, 'object.id');
        $payment = Payment::query()
            ->where('provider', 'yookassa')
            ->where('provider_payment_id', $providerPaymentId)
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'object.id' => 'Payment was not found.',
            ]);
        }

        if ($attributes['event'] === 'payment.canceled') {
            if ($payment->status === Payment::STATUS_PENDING) {
                $payment->forceFill([
                    'status' => Payment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'provider_payload' => $request->all(),
                ])->save();
            }

            return response()->json([
                'payment' => new PaymentResource($payment->refresh()),
                'dispatched' => false,
            ]);
        }

        if ($attributes['event'] !== 'payment.succeeded') {
            return response()->json([
                'message' => 'Webhook event ignored.',
            ], 202);
        }

        $remotePayment = $provider->getPayment($providerPaymentId);

        if (! $this->isConfirmedYooKassaPayment($payment, $remotePayment)) {
            return response()->json([
                'message' => 'Webhook payment is not confirmed.',
            ], 202);
        }

        $payment->forceFill([
            'provider_payload' => $remotePayment,
        ])->save();

        $dispatched = $activation->markPaidAndDispatch($payment);

        return response()->json([
            'payment' => new PaymentResource($payment->refresh()),
            'dispatched' => $dispatched,
        ]);
    }

    /**
     * @param  array<string, mixed>  $remotePayment
     */
    private function isConfirmedYooKassaPayment(Payment $payment, array $remotePayment): bool
    {
        return data_get($remotePayment, 'status') === 'succeeded'
            && data_get($remotePayment, 'paid') === true
            && data_get($remotePayment, 'amount.value') === number_format($payment->amount / 100, 2, '.', '')
            && data_get($remotePayment, 'amount.currency') === $payment->currency;
    }
}
