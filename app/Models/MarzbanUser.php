<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'subscription_id',
    'username',
    'status',
    'data_limit_bytes',
    'subscription_url',
    'raw_response',
])]
class MarzbanUser extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MERGED = 'merged';

    protected function casts(): array
    {
        return [
            'data_limit_bytes' => 'integer',
            'raw_response' => 'array',
        ];
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function subscriptionUrl(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (! $value || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                return $value;
            }

            return rtrim((string) config('marzban.base_url'), '/').'/'.ltrim($value, '/');
        });
    }

    /**
     * @return BelongsTo<User, MarzbanUser>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subscription, MarzbanUser>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
