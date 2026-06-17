<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'roman@example.com']);

        $this->postJsonWithCsrf('/api/auth/forgot-password', [
            'email' => 'roman@example.com',
        ])->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_reset_link_points_to_spa_reset_password_page(): void
    {
        config(['app.frontend_url' => 'https://app.cors-port.ru']);
        Notification::fake();

        $user = User::factory()->create(['email' => 'roman@example.com']);

        $this->postJsonWithCsrf('/api/auth/forgot-password', [
            'email' => 'roman@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $actionUrl = $notification->toMail($user)->actionUrl;

            return str_starts_with($actionUrl, 'https://app.cors-port.ru/app/reset-password?')
                && str_contains($actionUrl, 'email=roman%40example.com')
                && str_contains($actionUrl, 'token=');
        });
    }

    public function test_forgot_password_sends_russian_reset_email_for_russian_locale(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'roman@example.com',
            'locale' => 'en',
        ]);

        $this
            ->withHeader('X-Locale', 'ru')
            ->postJsonWithCsrf('/api/auth/forgot-password', [
                'email' => 'roman@example.com',
            ])->assertOk();

        $this->assertSame('ru', $user->refresh()->locale);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Сброс пароля'
                && $mail->actionText === 'Сбросить пароль';
        });
    }

    public function test_forgot_password_sends_english_reset_email_for_english_locale(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'roman@example.com',
            'locale' => 'ru',
        ]);

        $this
            ->withHeader('X-Locale', 'en')
            ->postJsonWithCsrf('/api/auth/forgot-password', [
                'email' => 'roman@example.com',
            ])->assertOk();

        $this->assertSame('en', $user->refresh()->locale);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Reset password'
                && $mail->actionText === 'Reset password';
        });
    }

    public function test_forgot_password_rejects_unknown_email(): void
    {
        Notification::fake();

        $this->postJsonWithCsrf('/api/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create([
            'email' => 'roman@example.com',
            'password' => Hash::make('old-password'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->postJsonWithCsrf('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'roman@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'roman@example.com']);

        $this->postJsonWithCsrf('/api/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'roman@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    private function postJsonWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson($uri, $data);
    }
}
