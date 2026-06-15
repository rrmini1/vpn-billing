<?php

namespace Tests\Feature;

use App\Exceptions\Marzban\MarzbanApiException;
use App\Models\MarzbanUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TrafficApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_traffic(): void
    {
        $this->getJson('/api/traffic')
            ->assertUnauthorized();
    }

    public function test_traffic_returns_null_without_active_subscription(): void
    {
        $user = User::factory()->create();

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->getJson('/api/traffic')
            ->assertOk()
            ->assertExactJson([
                'data' => null,
            ]);
    }

    public function test_authenticated_user_can_fetch_traffic_from_marzban(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'price_amount' => 0,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);

        MarzbanUser::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'username' => 'u'.$user->id.'_start_abcdefgh',
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => 53687091200,
            'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('u'.$user->id.'_start_abcdefgh')
            ->andReturn([
                'username' => 'u'.$user->id.'_start_abcdefgh',
                'status' => 'active',
                'data_limit' => 53687091200,
                'used_traffic' => 1073741824,
                'lifetime_used_traffic' => 2147483648,
                'subscription_url' => 'https://panel.cors-port.ru/sub/test-token/',
            ]);
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->getJson('/api/traffic')
            ->assertOk()
            ->assertJsonPath('data.username', 'u'.$user->id.'_start_abcdefgh')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.data_limit_bytes', 53687091200)
            ->assertJsonPath('data.used_traffic_bytes', 1073741824)
            ->assertJsonPath('data.remaining_traffic_bytes', 52613349376)
            ->assertJsonPath('data.usage_percent', 2)
            ->assertJsonPath('data.lifetime_used_traffic_bytes', 2147483648)
            ->assertJsonPath('data.subscription_url', 'https://panel.cors-port.ru/sub/test-token/');
    }

    public function test_traffic_caps_usage_percent_at_100(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create([
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
            'subscription_id' => $subscription->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => 1073741824,
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->andReturn([
                'username' => 'u'.$user->id.'_trial_abcdefgh',
                'status' => 'active',
                'data_limit' => 1073741824,
                'used_traffic' => 2147483648,
            ]);
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->getJson('/api/traffic')
            ->assertOk()
            ->assertJsonPath('data.remaining_traffic_bytes', 0)
            ->assertJsonPath('data.usage_percent', 100);
    }

    public function test_marzban_failure_returns_bad_gateway(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::query()->create([
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
            'subscription_id' => $subscription->id,
            'username' => 'u'.$user->id.'_trial_abcdefgh',
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => 1073741824,
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->andThrow(new MarzbanApiException('Marzban API request failed.', 500));
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->actingAs($user)
            ->getJson('/api/traffic')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not fetch traffic from Marzban.');
    }
}
