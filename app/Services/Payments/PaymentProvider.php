<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentProvider
{
    public function createPayment(Payment $payment): PaymentProviderResult;
}
