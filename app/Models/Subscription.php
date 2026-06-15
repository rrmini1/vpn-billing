<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'plan_id',
    'plan_code',
    'plan_name',
    'traffic_limit_bytes',
    'price_amount',
    'currency',
    'status',
    'started_at',
    'ended_at',
])]
class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    protected function casts(): array
    {
        return [
            'traffic_limit_bytes' => 'integer',
            'price_amount' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, Subscription>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, Subscription>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasOne<MarzbanUser>
     */
    public function marzbanUser(): HasOne
    {
        return $this->hasOne(MarzbanUser::class);
    }
}
