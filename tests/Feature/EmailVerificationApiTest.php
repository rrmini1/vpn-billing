<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmailVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $this->postJsonWithCsrf('/api/auth/register', [
            'name' => 'Roman',
            'email' => 'roman@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertCreated();

        Notification::assertSentTo(
            User::query()->where('email', 'roman@example.com')->firstOrFail(),
            VerifyEmailNotification::class,
        );
    }

    public function test_registration_stores_requested_locale_for_verification_email(): void
    {
        Notification::fake();

        $this
            ->withHeader('X-Locale', 'en')
            ->postJsonWithCsrf('/api/auth/register', [
                'name' => 'Roman',
                'email' => 'roman@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'roman@example.com',
            'locale' => 'en',
        ]);
    }

    public function test_authenticated_user_can_request_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $this->postJsonWithCsrf('/api/email/verification-notification')
            ->assertAccepted()
            ->assertJsonPath('message', 'Verification link sent.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resending_verification_notification_updates_user_locale(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['locale' => 'ru']);

        $this->actingAs($user);

        $this
            ->withHeader('X-Locale', 'en')
            ->postJsonWithCsrf('/api/email/verification-notification')
            ->assertAccepted();

        $this->assertSame('en', $user->refresh()->locale);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_already_verified_user_gets_empty_response_when_requesting_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJsonWithCsrf('/api/email/verification-notification')
            ->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_authenticated_user_can_verify_email_with_signed_link(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $this->getJson($this->verificationUrl($user))
            ->assertOk()
            ->assertJsonPath('message', 'Email verified.');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_guest_can_verify_email_from_browser_link_and_is_redirected_to_spa(): void
    {
        config(['app.frontend_url' => 'https://app.cors-port.ru']);

        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect('https://app.cors-port.ru/app/email-verified?status=success');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_email_verification_rejects_invalid_signature(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $this->getJson("/api/email/verify/{$user->id}/bad-hash?signature=bad")
            ->assertForbidden();
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }

    private function postJsonWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson($uri, $data);
    }
}
