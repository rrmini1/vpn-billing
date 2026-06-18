<?php

namespace Tests\Feature;

use App\Models\MarzbanUser;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Accounts\AccountMergeService;
use App\Services\Marzban\MarzbanService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AccountMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_account_can_be_merged_into_verified_email_account(): void
    {
        $target = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);
        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
            'telegram_username' => 'roman_tg',
            'telegram_first_name' => 'Roman',
        ]);
        $payment = Payment::query()->create([
            'user_id' => $source->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => $this->gb(50),
            'amount' => 10000,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PAID,
            'provider' => 'mock',
        ]);

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $marzban->shouldNotReceive('updateUserLimit');
        $marzban->shouldNotReceive('deleteUser');

        $merged = (new AccountMergeService($marzban))
            ->mergeTelegramAccountIntoVerifiedEmailAccount($source, $target);

        $this->assertSame($target->id, $merged->id);
        $this->assertSame(123456, $merged->telegram_id);
        $this->assertSame('roman_tg', $merged->telegram_username);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $source->id,
            'telegram_id' => null,
            'merged_into_user_id' => $target->id,
        ]);
    }

    public function test_merge_requires_verified_email_account_as_target(): void
    {
        $target = User::factory()->unverified()->create([
            'email' => 'roman@example.com',
            'telegram_id' => null,
        ]);
        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Target account must have a verified email.');

        (new AccountMergeService(Mockery::mock(MarzbanService::class)))
            ->mergeTelegramAccountIntoVerifiedEmailAccount($source, $target);
    }

    public function test_source_active_subscription_is_moved_when_target_has_no_active_subscription(): void
    {
        $target = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);
        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
        ]);
        $subscription = $this->createSubscription($source, 'start', $this->gb(50));
        $marzbanUser = $this->createMarzbanUser($source, $subscription, 'tg_user', $this->gb(50));

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldNotReceive('getUser');
        $marzban->shouldNotReceive('updateUserLimit');
        $marzban->shouldNotReceive('deleteUser');

        (new AccountMergeService($marzban))
            ->mergeTelegramAccountIntoVerifiedEmailAccount($source, $target);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $target->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('marzban_users', [
            'id' => $marzbanUser->id,
            'user_id' => $target->id,
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => $this->gb(50),
        ]);
    }

    public function test_active_subscription_limits_are_merged_when_both_accounts_have_access(): void
    {
        $target = User::factory()->create([
            'email' => 'roman@example.com',
            'email_verified_at' => now(),
            'telegram_id' => null,
        ]);
        $source = User::factory()->create([
            'email' => 'telegram-123456@telegram.local',
            'telegram_id' => 123456,
            'telegram_username' => 'roman_tg',
        ]);
        $targetSubscription = $this->createSubscription($target, 'standard', $this->gb(100));
        $sourceSubscription = $this->createSubscription($source, 'start', $this->gb(50));
        $targetMarzbanUser = $this->createMarzbanUser($target, $targetSubscription, 'email_user', $this->gb(100));
        $sourceMarzbanUser = $this->createMarzbanUser($source, $sourceSubscription, 'telegram_user', $this->gb(50));

        $marzban = Mockery::mock(MarzbanService::class);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('telegram_user')
            ->andReturn([
                'username' => 'telegram_user',
                'data_limit' => $this->gb(50),
                'used_traffic' => $this->gb(10),
            ]);
        $marzban->shouldReceive('getUser')
            ->once()
            ->with('email_user')
            ->andReturn([
                'username' => 'email_user',
                'data_limit' => $this->gb(100),
                'used_traffic' => $this->gb(20),
            ]);
        $marzban->shouldReceive('updateUserLimit')
            ->once()
            ->with('email_user', $this->gb(140))
            ->andReturn([
                'username' => 'email_user',
                'status' => 'active',
                'data_limit' => $this->gb(140),
                'subscription_url' => '/sub/email-token/',
            ]);
        $marzban->shouldReceive('deleteUser')
            ->once()
            ->with('telegram_user');

        (new AccountMergeService($marzban))
            ->mergeTelegramAccountIntoVerifiedEmailAccount($source, $target);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $targetSubscription->id,
            'user_id' => $target->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $sourceSubscription->id,
            'user_id' => $target->id,
            'status' => Subscription::STATUS_REPLACED,
        ]);
        $this->assertDatabaseHas('marzban_users', [
            'id' => $targetMarzbanUser->id,
            'user_id' => $target->id,
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => $this->gb(140),
            'subscription_url' => '/sub/email-token/',
        ]);
        $this->assertDatabaseHas('marzban_users', [
            'id' => $sourceMarzbanUser->id,
            'user_id' => $target->id,
            'status' => MarzbanUser::STATUS_MERGED,
        ]);
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
    }

    private function createSubscription(User $user, string $planCode, int $trafficLimitBytes): Subscription
    {
        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => $planCode,
            'plan_name' => ucfirst($planCode),
            'traffic_limit_bytes' => $trafficLimitBytes,
            'price_amount' => 10000,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    private function createMarzbanUser(User $user, Subscription $subscription, string $username, int $dataLimitBytes): MarzbanUser
    {
        return MarzbanUser::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'username' => $username,
            'status' => MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => $dataLimitBytes,
            'subscription_url' => '/sub/'.$username.'/',
        ]);
    }

    private function gb(int $value): int
    {
        return $value * 1024 * 1024 * 1024;
    }
}
