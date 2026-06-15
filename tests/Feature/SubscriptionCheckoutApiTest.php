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

class SubscriptionCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_updates_existing_marzban_user_and_replaces_current_subscription(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
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

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('u'.$user->id.'_trial_abcdefgh')
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'data_limit' => 1073741824,
                'used_traffic' => 268435456,
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
        $marzban->shouldNotReceive('createUser');
        $this->app->instance(MarzbanService::class, $marzban);

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/checkout', [
                'plan_code' => 'start',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.code', 'start')
            ->assertJsonPath('data.plan.traffic_limit_bytes', 53687091200)
            ->assertJsonPath('data.marzban_user.username', 'u'.$user->id.'_trial_abcdefgh')
            ->assertJsonPath('data.marzban_user.data_limit_bytes', 54760833024)
            ->assertJsonPath('data.marzban_user.subscription_url', 'https://panel.cors-port.ru/sub/test-token/');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $trial->id,
            'user_id' => $user->id,
            'plan_code' => 'trial',
            'status' => Subscription::STATUS_REPLACED,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'start',
            'traffic_limit_bytes' => 53687091200,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('marzban_users', [
            'user_id' => $user->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'data_limit_bytes' => 54760833024,
            'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
        ]);
    }

    public function test_checkout_adds_full_plan_traffic_when_existing_limit_is_already_exceeded(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
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
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('u'.$user->id.'_trial_abcdefgh')
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'data_limit' => 1073741824,
                'used_traffic' => 2147483648,
            ]);
        $marzban->shouldReceive('updateUserLimit')
            ->once()
            ->with('u'.$user->id.'_trial_abcdefgh', 55834574848)
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'status' => 'active',
                'data_limit' => 55834574848,
            ]);
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/checkout', [
                'plan_code' => 'start',
            ])
            ->assertCreated()
            ->assertJsonPath('data.marzban_user.data_limit_bytes', 55834574848);
    }

    public function test_checkout_creates_marzban_user_when_user_has_no_existing_access(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $marzban->shouldReceive('createUser')
            ->once()
            ->with(
                Mockery::pattern('/^u'.$user->id.'_standard_[a-z0-9]{8}$/'),
                161061273600,
                'Checkout subscription for user #'.$user->id.' plan standard',
            )
            ->andReturn([
                'username' => 'u'.$user->id.'_standard_abcdefgh',
                'status' => 'active',
                'data_limit' => 161061273600,
                'subscription_url' => '/sub/standard-token/',
            ]);
        $marzban->shouldNotReceive('updateUserLimit');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/checkout', [
                'plan_code' => 'standard',
            ])
            ->assertCreated()
            ->assertJsonPath('data.plan.code', 'standard')
            ->assertJsonPath('data.marzban_user.username', 'u'.$user->id.'_standard_abcdefgh')
            ->assertJsonPath('data.marzban_user.subscription_url', 'https://panel.cors-port.ru/sub/standard-token/');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'standard',
            'traffic_limit_bytes' => 161061273600,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_checkout_rejects_trial_plan(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $marzban->shouldNotReceive('createUser');
        $marzban->shouldNotReceive('updateUserLimit');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/checkout', [
                'plan_code' => 'trial',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selected plan is not available for checkout.');
    }

    public function test_checkout_failure_does_not_replace_current_subscription(): void
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
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
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->andThrow(new MarzbanApiException('Marzban API request failed.', 500));
        $marzban->shouldNotReceive('updateUserLimit');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/subscriptions/checkout', [
                'plan_code' => 'start',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not activate subscription in Marzban.');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $trial->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'user_id' => $user->id,
            'plan_code' => 'start',
        ]);
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->postJson('/api/subscriptions/checkout', [
            'plan_code' => 'start',
        ])->assertUnauthorized();
    }
}
