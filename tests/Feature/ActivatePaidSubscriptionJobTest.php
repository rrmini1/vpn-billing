<?php

namespace Tests\Feature;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Jobs\ActivatePaidSubscriptionJob;
use App\Models\MarzbanUser;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use App\Services\Subscriptions\SubscriptionActivator;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ActivatePaidSubscriptionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_activates_paid_payment_and_marks_activation_succeeded(): void
    {
        $this->seed(PlanSeeder::class);
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
            'status' => Payment::STATUS_PAID,
            'activation_status' => Payment::ACTIVATION_PENDING,
            'provider' => 'mock',
            'paid_at' => now(),
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('u'.$user->id.'_trial_abcdefgh')
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'data_limit' => 1073741824,
                'used_traffic' => 0,
            ]);
        $marzban->shouldReceive('updateUserLimit')
            ->once()
            ->with('u'.$user->id.'_trial_abcdefgh', 54760833024)
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'status' => 'active',
                'data_limit' => 54760833024,
                'subscription_url' => '/sub/test-token/',
            ]);
        $this->app->instance(MarzbanService::class, $marzban);

        app(ActivatePaidSubscriptionJob::class, ['paymentId' => $payment->id])->handle(
            app(MarzbanService::class),
            app(SubscriptionActivator::class),
        );

        $payment->refresh();

        $this->assertSame(Payment::ACTIVATION_SUCCEEDED, $payment->activation_status);
        $this->assertNull($payment->activation_error);
        $this->assertNotNull($payment->activated_at);
        $this->assertNotNull($payment->subscription_id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $trial->id,
            'status' => Subscription::STATUS_REPLACED,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $payment->subscription_id,
            'user_id' => $user->id,
            'plan_code' => 'start',
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('marzban_users', [
            'user_id' => $user->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'data_limit_bytes' => 54760833024,
        ]);
    }

    public function test_job_marks_activation_failed_when_marzban_fails(): void
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
            'activation_status' => Payment::ACTIVATION_PENDING,
            'provider' => 'mock',
            'paid_at' => now(),
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('createUser')
            ->once()
            ->andThrow(new MarzbanApiException('Marzban API request failed.', 500));
        $this->app->instance(MarzbanService::class, $marzban);

        $this->expectException(MarzbanApiException::class);

        try {
            app(ActivatePaidSubscriptionJob::class, ['paymentId' => $payment->id])->handle(
                app(MarzbanService::class),
                app(SubscriptionActivator::class),
            );
        } finally {
            $payment->refresh();

            $this->assertSame(Payment::ACTIVATION_FAILED, $payment->activation_status);
            $this->assertSame('Marzban API request failed.', $payment->activation_error);
            $this->assertNull($payment->subscription_id);
            $this->assertDatabaseMissing('subscriptions', [
                'user_id' => $user->id,
                'plan_code' => 'start',
            ]);
        }
    }
}
