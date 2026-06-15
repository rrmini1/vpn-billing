<?php

namespace Tests\Feature;

use App\Models\MarzbanUser;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_profile(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile_without_subscription(): void
    {
        $user = User::factory()->create([
            'name' => 'Roman',
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => 123456789,
            'telegram_username' => 'romanvpn',
            'telegram_first_name' => 'Roman',
            'telegram_last_name' => 'Dev',
            'telegram_photo_url' => 'https://example.com/avatar.jpg',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Roman')
            ->assertJsonPath('data.user.email', 'roman@example.com')
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.user.telegram.linked', true)
            ->assertJsonPath('data.user.telegram.id', 123456789)
            ->assertJsonPath('data.user.telegram.username', 'romanvpn')
            ->assertJsonPath('data.current_subscription', null)
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_profile_includes_current_subscription(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
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
            'subscription_url' => '/sub/test-token/',
        ]);

        $this
            ->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonPath('data.user.telegram.linked', false)
            ->assertJsonPath('data.current_subscription.id', $subscription->id)
            ->assertJsonPath('data.current_subscription.plan.code', 'start')
            ->assertJsonPath('data.current_subscription.marzban_user.username', 'u'.$user->id.'_start_abcdefgh')
            ->assertJsonPath('data.current_subscription.marzban_user.subscription_url', 'https://panel.cors-port.ru/sub/test-token/');
    }
}
