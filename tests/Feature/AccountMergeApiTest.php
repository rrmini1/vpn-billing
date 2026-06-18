<?php

namespace Tests\Feature;

use App\Mail\AttachEmailConfirmationMail;
use App\Models\AccountMergeToken;
use App\Models\EmailAttachToken;
use App\Models\User;
use App\Notifications\AccountMergeConfirmationNotification;
use App\Services\Marzban\MarzbanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class AccountMergeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_user_can_start_email_merge_confirmation(): void
    {
        Notification::fake();

        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);
        $target = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
        ]);

        $this
            ->actingAs($source)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/merge/email/start', [
                'email' => 'roman@example.com',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', 'merge_confirmation_sent');

        $this->assertDatabaseHas('account_merge_tokens', [
            'source_user_id' => $source->id,
            'target_user_id' => $target->id,
            'confirmed_at' => null,
        ]);

        Notification::assertSentTo($target, AccountMergeConfirmationNotification::class);
    }

    public function test_telegram_user_can_start_new_email_attach_confirmation(): void
    {
        Mail::fake();

        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);

        $this
            ->actingAs($source)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/email/start', [
                'email' => 'new@example.com',
            ])
            ->assertAccepted()
            ->assertJsonPath('status', 'email_attach_confirmation_sent');

        $this->assertDatabaseHas('email_attach_tokens', [
            'user_id' => $source->id,
            'email' => 'new@example.com',
            'confirmed_at' => null,
        ]);

        Mail::assertSent(AttachEmailConfirmationMail::class, function (AttachEmailConfirmationMail $mail): bool {
            return $mail->hasTo('new@example.com');
        });
    }

    public function test_telegram_user_can_complete_new_email_attach_with_password(): void
    {
        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
            'email_verified_at' => null,
        ]);
        [$token] = EmailAttachToken::issue($source, 'new@example.com');
        $plainToken = $this->plainEmailAttachToken($token);

        $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/email/complete', [
                'token' => $plainToken,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'email_attached');

        $source->refresh();

        $this->assertSame('new@example.com', $source->email);
        $this->assertNotNull($source->email_verified_at);
        $this->assertTrue(Hash::check('new-password', $source->password));
        $this->assertNotNull($token->refresh()->confirmed_at);
    }

    public function test_email_merge_rejects_unverified_email_account(): void
    {
        Notification::fake();

        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);
        User::factory()->unverified()->create([
            'email' => 'roman@example.com',
        ]);

        $this
            ->actingAs($source)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/merge/email/start', [
                'email' => 'roman@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Email exists but is not verified. Please verify this email before merging accounts.');

        $this->assertDatabaseCount('account_merge_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_universal_email_start_rejects_unverified_existing_email_with_clear_message(): void
    {
        Notification::fake();

        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);
        User::factory()->unverified()->create([
            'email' => 'roman@example.com',
        ]);

        $this
            ->actingAs($source)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/email/start', [
                'email' => 'roman@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email exists but is not verified. Please verify this email before merging accounts.');
    }

    public function test_email_merge_confirmation_merges_accounts(): void
    {
        Notification::fake();

        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
            'telegram_username' => 'roman_tg',
        ]);
        $target = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);

        $this
            ->actingAs($source)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/account/merge/email/start', [
                'email' => 'roman@example.com',
            ])
            ->assertAccepted();

        $plainToken = null;
        Notification::assertSentTo($target, AccountMergeConfirmationNotification::class, function (AccountMergeConfirmationNotification $notification) use ($target, &$plainToken): bool {
            $actionUrl = $notification->toMail($target)->actionUrl;
            $query = [];
            parse_str((string) parse_url($actionUrl, PHP_URL_QUERY), $query);
            $plainToken = $query['token'] ?? null;

            return is_string($plainToken) && $plainToken !== '';
        });

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $marzban->shouldNotReceive('updateUserLimit');
        $marzban->shouldNotReceive('deleteUser');
        $this->app->instance(MarzbanService::class, $marzban);

        $this
            ->getJson('/api/account/merge/email/confirm?token='.$plainToken)
            ->assertOk()
            ->assertJsonPath('status', 'merged');

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'telegram_id' => 123456,
            'telegram_username' => 'roman_tg',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $source->id,
            'telegram_id' => null,
            'merged_into_user_id' => $target->id,
        ]);

        $this->assertNotNull(AccountMergeToken::query()->firstOrFail()->confirmed_at);
    }

    public function test_email_merge_confirmation_rejects_invalid_token(): void
    {
        $this
            ->getJson('/api/account/merge/email/confirm?token=invalid')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Merge confirmation token is invalid.');
    }

    private function plainEmailAttachToken(EmailAttachToken $token): string
    {
        $plainToken = 'test-email-attach-token';

        $token->forceFill([
            'token_hash' => EmailAttachToken::hashToken($plainToken),
        ])->save();

        return $plainToken;
    }
}
