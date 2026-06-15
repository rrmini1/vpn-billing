<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Marzban\MarzbanService;
use App\Services\Subscriptions\SubscriptionActivator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActivatePaidSubscriptionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $paymentId) {}

    public function handle(MarzbanService $marzban, SubscriptionActivator $activator): void
    {
        $payment = Payment::query()
            ->with(['user', 'plan'])
            ->findOrFail($this->paymentId);

        if ($payment->status !== Payment::STATUS_PAID
            || $payment->activation_status === Payment::ACTIVATION_SUCCEEDED
            || ! $payment->plan
        ) {
            return;
        }

        $payment->forceFill([
            'activation_status' => Payment::ACTIVATION_PROCESSING,
            'activation_error' => null,
        ])->save();

        try {
            $subscription = $activator->activate($payment->user, $payment->plan, $marzban);

            DB::transaction(function () use ($payment, $subscription): void {
                $payment->forceFill([
                    'subscription_id' => $subscription->id,
                    'activation_status' => Payment::ACTIVATION_SUCCEEDED,
                    'activation_error' => null,
                    'activated_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            $payment->forceFill([
                'activation_status' => Payment::ACTIVATION_FAILED,
                'activation_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }
}
