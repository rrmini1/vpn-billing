<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'plan_id',
    'subscription_id',
    'plan_code',
    'plan_name',
    'traffic_limit_bytes',
    'amount',
    'currency',
    'status',
    'activation_status',
    'activation_error',
    'provider',
    'provider_payment_id',
    'confirmation_url',
    'expires_at',
    'provider_payload',
    'metadata',
    'activated_at',
    'paid_at',
    'failed_at',
    'cancelled_at',
])]
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVATION_PENDING = 'pending';

    public const ACTIVATION_PROCESSING = 'processing';

    public const ACTIVATION_SUCCEEDED = 'succeeded';

    public const ACTIVATION_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'traffic_limit_bytes' => 'integer',
            'amount' => 'integer',
            'metadata' => 'array',
            'provider_payload' => 'array',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, Payment>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Plan, Payment>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Subscription, Payment>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
