<?php

namespace App\Services\Accounts;

use App\Models\MarzbanUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use DomainException;
use Illuminate\Support\Facades\DB;

class AccountMergeService
{
    public function __construct(
        private readonly MarzbanService $marzban,
    ) {}

    public function mergeTelegramAccountIntoVerifiedEmailAccount(User $source, User $target): User
    {
        $this->assertCanMerge($source, $target);

        $sourceActiveMarzbanUser = $this->activeMarzbanUser($source);
        $targetActiveMarzbanUser = $this->activeMarzbanUser($target);
        $targetMarzbanResponse = null;

        if ($sourceActiveMarzbanUser && $targetActiveMarzbanUser) {
            $targetMarzbanResponse = $this->extendTargetMarzbanLimit(
                $sourceActiveMarzbanUser,
                $targetActiveMarzbanUser,
            );

            $this->marzban->deleteUser($sourceActiveMarzbanUser->username);
        }

        return DB::transaction(function () use ($source, $target, $sourceActiveMarzbanUser, $targetActiveMarzbanUser, $targetMarzbanResponse): User {
            $now = now();

            if ($sourceActiveMarzbanUser && $targetActiveMarzbanUser) {
                $this->replaceSourceActiveSubscriptions($source, $now);
                $this->markSourceMarzbanUserAsMerged($sourceActiveMarzbanUser, $target, $targetActiveMarzbanUser, $now);
                $this->updateTargetMarzbanUser($targetActiveMarzbanUser, $targetMarzbanResponse);
            }

            $this->moveOwnedRecords($source, $target);
            $this->moveTelegramIdentity($source, $target);

            $source->forceFill([
                'merged_into_user_id' => $target->id,
                'merged_at' => $now,
            ])->save();

            return $target->refresh();
        });
    }

    private function assertCanMerge(User $source, User $target): void
    {
        if ($source->is($target)) {
            throw new DomainException('Cannot merge an account into itself.');
        }

        if ($source->isMerged() || $target->isMerged()) {
            throw new DomainException('Merged accounts cannot be merged again.');
        }

        if (! $source->telegram_id) {
            throw new DomainException('Source account does not have Telegram identity.');
        }

        if ($target->hasTechnicalTelegramEmail() || ! $target->hasVerifiedEmail()) {
            throw new DomainException('Target account must have a verified email.');
        }

        if ($target->telegram_id && $target->telegram_id !== $source->telegram_id) {
            throw new DomainException('Target account already has another Telegram identity.');
        }
    }

    private function activeMarzbanUser(User $user): ?MarzbanUser
    {
        $subscription = $user
            ->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('marzbanUser')
            ->latest('id')
            ->first();

        return $subscription?->marzbanUser;
    }

    /**
     * @return array<string, mixed>
     */
    private function extendTargetMarzbanLimit(MarzbanUser $source, MarzbanUser $target): array
    {
        $sourceState = $this->marzban->getUser($source->username);
        $targetState = $this->marzban->getUser($target->username);
        $newTargetLimitBytes = $this->newTargetLimitBytes($sourceState, $targetState);

        return $this->marzban->updateUserLimit($target->username, $newTargetLimitBytes);
    }

    /**
     * @param  array<string, mixed>  $sourceState
     * @param  array<string, mixed>  $targetState
     */
    private function newTargetLimitBytes(array $sourceState, array $targetState): int
    {
        $sourceRemainingBytes = $this->remainingTrafficBytes($sourceState);
        $targetUsedBytes = max(0, (int) ($targetState['used_traffic'] ?? 0));
        $targetLimitBytes = $targetState['data_limit'] ?? null;

        if (! is_numeric($targetLimitBytes)) {
            return $targetUsedBytes + $sourceRemainingBytes;
        }

        return max($targetUsedBytes, (int) $targetLimitBytes) + $sourceRemainingBytes;
    }

    /**
     * @param  array<string, mixed>  $marzbanUser
     */
    private function remainingTrafficBytes(array $marzbanUser): int
    {
        $usedTrafficBytes = max(0, (int) ($marzbanUser['used_traffic'] ?? 0));
        $dataLimitBytes = $marzbanUser['data_limit'] ?? null;

        if (! is_numeric($dataLimitBytes)) {
            return 0;
        }

        return max(0, (int) $dataLimitBytes - $usedTrafficBytes);
    }

    private function replaceSourceActiveSubscriptions(User $source, mixed $now): void
    {
        $source->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->update([
                'status' => Subscription::STATUS_REPLACED,
                'ended_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function markSourceMarzbanUserAsMerged(MarzbanUser $source, User $target, MarzbanUser $targetMarzbanUser, mixed $now): void
    {
        $source->forceFill([
            'user_id' => $target->id,
            'status' => MarzbanUser::STATUS_MERGED,
            'raw_response' => [
                ...($source->raw_response ?? []),
                'merged_into_username' => $targetMarzbanUser->username,
                'merged_at' => $now->toISOString(),
            ],
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    private function updateTargetMarzbanUser(MarzbanUser $target, ?array $response): void
    {
        if (! $response) {
            return;
        }

        $target->forceFill([
            'status' => $response['status'] ?? MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => $response['data_limit'] ?? $target->data_limit_bytes,
            'subscription_url' => $response['subscription_url'] ?? $target->getRawOriginal('subscription_url'),
            'raw_response' => $response,
        ])->save();
    }

    private function moveOwnedRecords(User $source, User $target): void
    {
        $source->payments()->update(['user_id' => $target->id]);
        $source->subscriptions()->update(['user_id' => $target->id]);
        $source->marzbanUsers()->update(['user_id' => $target->id]);
    }

    private function moveTelegramIdentity(User $source, User $target): void
    {
        $telegramAttributes = [
            'telegram_id' => $source->telegram_id,
            'telegram_username' => $source->telegram_username,
            'telegram_first_name' => $source->telegram_first_name,
            'telegram_last_name' => $source->telegram_last_name,
            'telegram_photo_url' => $source->telegram_photo_url,
            'telegram_auth_date' => $source->telegram_auth_date,
        ];

        $source->forceFill([
            'telegram_id' => null,
            'telegram_username' => null,
            'telegram_first_name' => null,
            'telegram_last_name' => null,
            'telegram_photo_url' => null,
            'telegram_auth_date' => null,
        ])->save();

        $target->forceFill($telegramAttributes)->save();
    }
}
