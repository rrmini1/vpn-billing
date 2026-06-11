<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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

        Notification::assertSentTo($user, ResetPassword::class);
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
