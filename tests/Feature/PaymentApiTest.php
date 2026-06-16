<?php

namespace Tests\Feature;

use App\Jobs\ActivatePaidSubscriptionJob;
use App\Models\MarzbanUser;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_pending_payment_for_paid_plan(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments', [
                'plan_code' => 'start',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'mock')
            ->assertJsonPath('data.plan.code', 'start')
            ->assertJsonPath('data.amount', 0)
            ->assertJsonPath('data.currency', 'RUB');

        $this->assertStringStartsWith(
            'http://localhost:8083/mock-payments/mock_',
            $response->json('data.confirmation_url'),
        );
        $this->assertNotNull($response->json('data.expires_at'));

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'plan_code' => 'start',
            'status' => Payment::STATUS_PENDING,
            'amount' => 0,
            'currency' => 'RUB',
        ]);

        $payment = Payment::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertStringStartsWith('mock_', $payment->provider_payment_id);
        $this->assertStringStartsWith('http://localhost:8083/mock-payments/mock_', $payment->confirmation_url);
        $this->assertNotNull($payment->expires_at);
        $this->assertSame($payment->provider_payment_id, $payment->provider_payload['payment_id']);
    }

    public function test_user_can_create_yookassa_payment_for_paid_plan(): void
    {
        config([
            'payments.default' => 'yookassa',
            'payments.yookassa.shop_id' => 'test-shop-id',
            'payments.yookassa.secret_key' => 'test-secret-key',
            'payments.yookassa.return_url' => 'https://app.cors-port.ru/app/payments',
        ]);
        Http::fake([
            'https://api.yookassa.ru/v3/payments' => Http::response([
                'id' => '2f1f0000-000f-5000-9000-000000000001',
                'status' => 'pending',
                'paid' => false,
                'amount' => [
                    'value' => '0.00',
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'confirmation_url' => 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=abc',
                ],
                'expires_at' => '2026-06-16T13:30:00.000Z',
            ], 200),
        ]);

        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments', [
                'plan_code' => 'start',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.provider', 'yookassa')
            ->assertJsonPath('data.provider_payment_id', '2f1f0000-000f-5000-9000-000000000001')
            ->assertJsonPath('data.confirmation_url', 'https://yoomoney.ru/checkout/payments/v2/contract?orderId=abc');

        $payment = Payment::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame('yookassa', $payment->provider);
        $this->assertSame('2f1f0000-000f-5000-9000-000000000001', $payment->provider_payment_id);

        Http::assertSent(function ($request) use ($payment, $user): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.yookassa.ru/v3/payments'
                && $request->hasHeader('Idempotence-Key')
                && $request['capture'] === true
                && $request['amount']['value'] === '0.00'
                && $request['amount']['currency'] === 'RUB'
                && $request['confirmation']['type'] === 'redirect'
                && $request['confirmation']['return_url'] === 'https://app.cors-port.ru/app/payments'
                && $request['metadata']['local_payment_id'] === (string) $payment->id
                && $request['metadata']['user_id'] === (string) $user->id
                && $request['metadata']['plan_code'] === 'start';
        });
    }

    public function test_user_can_list_own_payments(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Payment::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'amount' => 0,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
        ]);
        Payment::query()->create([
            'user_id' => $otherUser->id,
            'plan_code' => 'premium',
            'plan_name' => 'Премиум',
            'traffic_limit_bytes' => 536870912000,
            'amount' => 0,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plan.code', 'start');
    }

    public function test_simulate_paid_marks_payment_paid_and_dispatches_activation_job(): void
    {
        $this->seed(PlanSeeder::class);
        Queue::fake();

        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'start')->firstOrFail();
        $trial = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'trial',
            'plan_name' => 'Тест',
            'traffic_limit_bytes' => 1073741824,
            'price_amount' => 0,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
        MarzbanUser::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $trial->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => 1073741824,
            'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
        ]);
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'amount' => 0,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments/'.$payment->id.'/simulate-paid');

        $response
            ->assertAccepted()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.activation_status', 'pending')
            ->assertJsonPath('payment.plan.code', 'start');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
            'activation_status' => Payment::ACTIVATION_PENDING,
        ]);
        $this->assertNotNull($payment->refresh()->paid_at);

        Queue::assertPushed(
            ActivatePaidSubscriptionJob::class,
            fn (ActivatePaidSubscriptionJob $job): bool => $job->paymentId === $payment->id,
        );
    }

    public function test_simulate_paid_rejects_non_pending_payment(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', 'start')->firstOrFail();
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'amount' => 0,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PAID,
            'provider' => 'mock',
            'paid_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments/'.$payment->id.'/simulate-paid')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment is not pending.');
    }

    public function test_guest_cannot_access_payments(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();
        $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments', ['plan_code' => 'start'])
            ->assertUnauthorized();
    }
}
