<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TelegramLinkToken;
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

    public function test_telegram_login_reuses_existing_technical_email_user_without_telegram_id(): void
    {
        $user = User::factory()->create([
            'telegram_id' => null,
            'telegram_username' => null,
            'email' => 'telegram-123456@telegram.local',
        ]);

        $response = $this->postJsonWithCsrf('/api/auth/telegram/login', [
            'init_data' => $this->telegramInitData([
                'id' => 123456,
                'username' => 'roman',
                'first_name' => 'Roman',
            ]),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.telegram_id', 123456)
            ->assertJsonPath('user.telegram_username', 'roman');

        $this->assertAuthenticatedAs($user->refresh());
        $this->assertDatabaseCount('users', 1);
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

    public function test_authenticated_verified_user_can_create_telegram_link_token(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this
            ->postJsonWithCsrf('/api/auth/telegram/link-token')
            ->assertCreated()
            ->assertJsonStructure(['bot_url', 'expires_at']);

        $this->assertStringStartsWith(
            'https://t.me/CorsPortMain_bot?start=link_telegram_',
            $response->json('bot_url'),
        );
        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => $user->id,
            'confirmed_at' => null,
        ]);
    }

    public function test_telegram_link_token_confirmation_links_telegram_to_email_user(): void
    {
        $user = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);
        [$token] = TelegramLinkToken::issue($user);
        $plainToken = $this->plainTokenFromStoredToken($token);

        $this
            ->postJsonWithCsrf('/api/auth/telegram/link-token/confirm', [
                'token' => $plainToken,
                'init_data' => $this->telegramInitData([
                    'id' => 123456,
                    'username' => 'roman',
                    'first_name' => 'Roman',
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.telegram_id', 123456);

        $this->assertAuthenticatedAs($user->refresh());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'telegram_id' => 123456,
            'telegram_username' => 'roman',
        ]);
        $this->assertNotNull($token->refresh()->confirmed_at);
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

    private function plainTokenFromStoredToken(TelegramLinkToken $token): string
    {
        $plainToken = 'test-link-token';

        $token->forceFill([
            'token_hash' => TelegramLinkToken::hashToken($plainToken),
        ])->save();

        return $plainToken;
    }
}
