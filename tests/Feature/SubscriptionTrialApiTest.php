<?php

namespace Tests\Feature;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Models\MarzbanUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionTrialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_trial_subscription(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('createUser')
            ->once()
            ->with(
                Mockery::pattern('/^u'.$user->id.'_trial_[a-z0-9]{8}$/'),
                1073741824,
                'Trial subscription for user #'.$user->id,
            )
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'status' => 'active',
                'data_limit' => 1073741824,
                'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
                'links' => ['nl', 'ca', 'ru'],
            ]);

        $this->app->instance(MarzbanService::class, $marzban);

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/trial');

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.code', 'trial')
            ->assertJsonPath('data.plan.traffic_limit_bytes', 1073741824)
            ->assertJsonPath('data.marzban_user.username', 'u'.$user->id.'_trial_abcdefgh')
            ->assertJsonPath('data.marzban_user.subscription_url', 'https://panel.cors-port.ru/sub/test-token/');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'trial',
            'plan_name' => 'Тест',
            'traffic_limit_bytes' => 1073741824,
            'price_amount' => 0,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('marzban_users', [
            'user_id' => $user->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => 1073741824,
            'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
        ]);
    }

    public function test_guest_cannot_create_trial_subscription(): void
    {
        $this->postJson('/api/subscriptions/trial')
            ->assertUnauthorized();
    }

    public function test_user_cannot_create_trial_subscription_twice(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'trial',
            'plan_name' => 'Тест',
            'traffic_limit_bytes' => 1073741824,
            'price_amount' => 0,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('createUser');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/trial')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Trial subscription has already been issued.');
    }

    public function test_marzban_failure_does_not_create_subscription(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('createUser')
            ->once()
            ->andThrow(new MarzbanApiException('Marzban API request failed.', 500));

        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/trial')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not create Marzban user.');

        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'trial',
        ]);
        $this->assertDatabaseMissing('marzban_users', [
            'user_id' => $user->id,
        ]);
    }
}
