<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class YooKassaPaymentProvider implements PaymentProvider
{
    public function createPayment(Payment $payment): PaymentProviderResult
    {
        $payload = [
            'amount' => [
                'value' => $this->formatAmount($payment->amount),
                'currency' => $payment->currency,
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => (string) config('payments.yookassa.return_url'),
            ],
            'description' => $this->description($payment),
            'metadata' => [
                'local_payment_id' => (string) $payment->id,
                'user_id' => (string) $payment->user_id,
                'plan_code' => $payment->plan_code,
            ],
        ];

        $response = $this->http()
            ->withHeaders([
                'Idempotence-Key' => (string) Str::uuid(),
            ])
            ->post($this->url('/payments'), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('YooKassa payment creation failed.');
        }

        $data = $response->json();
        $providerPaymentId = data_get($data, 'id');

        if (! is_string($providerPaymentId) || $providerPaymentId === '') {
            throw new RuntimeException('YooKassa response does not contain payment id.');
        }

        return new PaymentProviderResult(
            provider: 'yookassa',
            providerPaymentId: $providerPaymentId,
            confirmationUrl: data_get($data, 'confirmation.confirmation_url'),
            expiresAt: $this->parseDate(data_get($data, 'expires_at')),
            payload: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $providerPaymentId): array
    {
        $response = $this->http()->get($this->url('/payments/'.$providerPaymentId));

        if (! $response->successful()) {
            throw new RuntimeException('YooKassa payment fetch failed.');
        }

        return $response->json();
    }

    private function http(): PendingRequest
    {
        $shopId = config('payments.yookassa.shop_id');
        $secretKey = config('payments.yookassa.secret_key');

        if (! is_string($shopId) || $shopId === '' || ! is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('YooKassa credentials are not configured.');
        }

        return Http::acceptJson()
            ->asJson()
            ->timeout((int) config('payments.yookassa.timeout', 10))
            ->withBasicAuth($shopId, $secretKey);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('payments.yookassa.base_url'), '/').$path;
    }

    private function formatAmount(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    private function description(Payment $payment): string
    {
        return Str::limit('Cors Port solutions: '.$payment->plan_name, 128, '');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }
}
