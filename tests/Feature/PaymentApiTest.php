<?php

namespace Tests\Feature;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Models\MarzbanUser;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'plan_code' => 'start',
            'status' => Payment::STATUS_PENDING,
            'amount' => 0,
            'currency' => 'RUB',
        ]);
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

    public function test_simulate_paid_activates_subscription_and_marks_payment_paid(): void
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
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
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

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments/'.$payment->id.'/simulate-paid');

        $response
            ->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('payment.plan.code', 'start')
            ->assertJsonPath('subscription.plan.code', 'start')
            ->assertJsonPath('subscription.marzban_user.data_limit_bytes', 54760833024);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PAID,
        ]);
        $this->assertNotNull($payment->refresh()->paid_at);
        $this->assertNotNull($payment->subscription_id);
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

    public function test_marzban_failure_does_not_mark_payment_paid(): void
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
            'status' => Payment::STATUS_PENDING,
            'provider' => 'mock',
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('createUser')
            ->once()
            ->andThrow(new MarzbanApiException('Marzban API request failed.', 500));
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/payments/'.$payment->id.'/simulate-paid')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not activate subscription in Marzban.');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'start',
        ]);
    }

    public function test_guest_cannot_access_payments(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();
        $this->postJson('/api/payments', ['plan_code' => 'start'])->assertUnauthorized();
    }
}
