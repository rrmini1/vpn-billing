<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    protected function casts(): array
    {
        return [
            'data_limit_bytes' => 'integer',
            'raw_response' => 'array',
        ];
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
