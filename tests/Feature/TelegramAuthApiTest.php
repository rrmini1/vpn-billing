<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TelegramAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_telegram_init_data(): void
    {
        $response = $this->postJsonWithCsrf('/api/auth/telegram/login', [
            'init_data' => $this->telegramInitData([
                'id' => 123456,
                'username' => 'roman',
                'first_name' => 'Roman',
                'last_name' => 'VPN',
                'photo_url' => 'https://t.me/i/userpic/320/roman.jpg',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.telegram_id', 123456)
            ->assertJsonPath('user.telegram_username', 'roman')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'telegram_id' => 123456,
            'telegram_username' => 'roman',
            'email' => 'telegram-123456@telegram.local',
        ]);
    }

    public function test_telegram_login_updates_existing_user_and_reuses_session_user(): void
    {
        $user = User::factory()->create([
            'telegram_id' => 123456,
            'telegram_username' => 'old_username',
        ]);

        $response = $this->postJsonWithCsrf('/api/auth/telegram/login', [
            'init_data' => $this->telegramInitData([
                'id' => 123456,
                'username' => 'new_username',
                'first_name' => 'Roman',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.telegram_username', 'new_username');

        $this->assertAuthenticatedAs($user->refresh());
    }

    public function test_telegram_login_rejects_invalid_signature(): void
    {
        $this->postJsonWithCsrf('/api/auth/telegram/login', [
            'init_data' => $this->telegramInitData(['id' => 123456]).'tampered=yes',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('init_data');
    }

    public function test_authenticated_user_can_link_telegram_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJsonWithCsrf('/api/auth/telegram/link', [
            'init_data' => $this->telegramInitData([
                'id' => 123456,
                'username' => 'roman',
                'first_name' => 'Roman',
            ]),
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.telegram_id', 123456);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'telegram_id' => 123456,
            'telegram_username' => 'roman',
        ]);
    }

    public function test_authenticated_user_cannot_link_telegram_account_used_by_another_user(): void
    {
        User::factory()->create(['telegram_id' => 123456]);
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJsonWithCsrf('/api/auth/telegram/link', [
            'init_data' => $this->telegramInitData(['id' => 123456]),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('init_data');
    }

    public function test_guest_cannot_link_telegram_account(): void
    {
        $this->postJsonWithCsrf('/api/auth/telegram/link', [
            'init_data' => $this->telegramInitData(['id' => 123456]),
        ])->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function telegramInitData(array $user, ?int $authDate = null): string
    {
        $data = [
            'auth_date' => (string) ($authDate ?? now()->timestamp),
            'query_id' => 'AAHdF6IQAAAAAN0XohDhrOrc',
            'user' => json_encode($user, JSON_UNESCAPED_SLASHES),
        ];

        ksort($data);

        $checkString = collect($data)
            ->map(fn (string $value, string $key): string => $key.'='.$value)
            ->implode("\n");

        $secret = hash_hmac('sha256', 'test-telegram-bot-token', 'WebAppData', true);
        $data['hash'] = hash_hmac('sha256', $checkString, $secret);

        return http_build_query($data);
    }

    private function postJsonWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson($uri, $data);
    }
}
