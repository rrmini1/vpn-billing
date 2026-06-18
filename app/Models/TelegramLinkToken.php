<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'token_hash',
    'expires_at',
    'confirmed_at',
])]
class TelegramLinkToken extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: TelegramLinkToken, 1: string}
     */
    public static function issue(User $user, int $ttlMinutes = 30): array
    {
        $plainToken = Str::random(32);

        $token = self::query()->create([
            'user_id' => $user->id,
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
     * @return BelongsTo<User, TelegramLinkToken>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
