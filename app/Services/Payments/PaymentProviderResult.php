<?php

namespace App\Services\Payments;

use Illuminate\Support\Carbon;

class PaymentProviderResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $providerPaymentId,
        public readonly ?string $confirmationUrl,
        public readonly ?Carbon $expiresAt,
        public readonly array $payload = [],
    ) {}
}
