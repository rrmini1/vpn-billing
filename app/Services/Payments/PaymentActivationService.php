<?php

namespace App\Services\Payments;

use App\Jobs\ActivatePaidSubscriptionJob;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentActivationService
{
    public function markPaidAndDispatch(Payment $payment): bool
    {
        if ($payment->status === Payment::STATUS_PAID) {
            return false;
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            return false;
        }

        DB::transaction(function () use ($payment): void {
            $payment->forceFill([
                'status' => Payment::STATUS_PAID,
                'activation_status' => Payment::ACTIVATION_PENDING,
                'activation_error' => null,
                'paid_at' => now(),
            ])->save();
        });

        ActivatePaidSubscriptionJob::dispatch($payment->id);

        return true;
    }
}
