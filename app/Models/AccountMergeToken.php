<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'source_user_id',
    'target_user_id',
    'token_hash',
    'expires_at',
    'confirmed_at',
])]
class AccountMergeToken extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: AccountMergeToken, 1: string}
     */
    public static function issue(User $source, User $target, int $ttlMinutes = 60): array
    {
        $plainToken = Str::random(64);

        $token = self::query()->create([
            'source_user_id' => $source->id,
            'target_user_id' => $target->id,
            'token_hash' => self::hashToken($plainToken),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return [$token, $plainToken];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isConfirmable(): bool
    {
        return $this->confirmed_at === null && $this->expires_at->isFuture();
    }

    public function markConfirmed(?Carbon $confirmedAt = null): void
    {
        $this->forceFill([
            'confirmed_at' => $confirmedAt ?? now(),
        ])->save();
    }

    /**
     * @return BelongsTo<User, AccountMergeToken>
     */
    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    /**
     * @return BelongsTo<User, AccountMergeToken>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
