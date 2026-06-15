<?php

namespace Tests\Feature;

use App\Jobs\ActivatePaidSubscriptionJob;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentWebhookApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_webhook_marks_pending_payment_paid_and_dispatches_activation_job(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $payment = $this->createPendingPayment();

        $this
            ->postJson('/api/webhooks/payments/mock', [
                'event' => 'payment.succeeded',
                'provider_payment_id' => 'mock-payment-1',
            ])
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.activation_status', 'pending')
            ->assertJsonPath('dispatched', true);

        Queue::assertPushed(
            ActivatePaidSubscriptionJob::class,
            fn (ActivatePaidSubscriptionJob $job): bool => $job->paymentId === $payment->id,
        );
    }

    public function test_mock_webhook_is_idempotent_for_already_paid_payment(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $payment = $this->createPendingPayment();
        $payment->forceFill([
            'status' => Payment::STATUS_PAID,
            'activation_status' => Payment::ACTIVATION_PENDING,
            'paid_at' => now(),
        ])->save();

        $this
            ->postJson('/api/webhooks/payments/mock', [
                'event' => 'payment.succeeded',
                'provider_payment_id' => 'mock-payment-1',
            ])
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.activation_status', 'pending')
            ->assertJsonPath('dispatched', false);

        Queue::assertNothingPushed();
    }

    public function test_mock_webhook_returns_validation_error_for_unknown_payment(): void
    {
        Queue::fake();

        $this
            ->postJson('/api/webhooks/payments/mock', [
                'event' => 'payment.succeeded',
                'provider_payment_id' => 'missing-payment',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('provider_payment_id');

        Queue::assertNothingPushed();
    }

    public function test_mock_webhook_ignores_unsupported_event(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $payment = $this->createPendingPayment();

        $this
            ->postJson('/api/webhooks/payments/mock', [
                'event' => 'payment.cancelled',
                'provider_payment_id' => 'mock-payment-1',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Webhook event ignored.');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PENDING,
        ]);
        Queue::assertNothingPushed();
    }

    private function createPendingPayment(): Payment
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'start')->firstOrFail();

        return Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'amount' => 0,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
            'provider_payment_id' => 'mock-payment-1',
        ]);
    }
}
