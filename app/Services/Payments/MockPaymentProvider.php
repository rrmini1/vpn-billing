<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Str;

class MockPaymentProvider implements PaymentProvider
{
    public function createPayment(Payment $payment): PaymentProviderResult
    {
        $providerPaymentId = 'mock_'.Str::uuid();
        $confirmationUrl = rtrim((string) config('app.url'), '/')
            .'/mock-payments/'.$providerPaymentId;

        return new PaymentProviderResult(
            provider: 'mock',
            providerPaymentId: $providerPaymentId,
            confirmationUrl: $confirmationUrl,
            expiresAt: now()->addMinutes((int) config('payments.mock.expires_in_minutes', 30)),
            payload: [
                'payment_id' => $providerPaymentId,
                'amount' => [
                    'value' => $payment->amount,
                    'currency' => $payment->currency,
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'url' => $confirmationUrl,
                ],
            ],
        );
    }
}
