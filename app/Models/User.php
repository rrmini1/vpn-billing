<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'merged_into_user_id',
    'merged_at',
    'locale',
    'telegram_id',
    'telegram_username',
    'telegram_first_name',
    'telegram_last_name',
    'telegram_photo_url',
    'telegram_auth_date',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'merged_at' => 'datetime',
            'telegram_auth_date' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Subscription>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasMany<MarzbanUser>
     */
    public function marzbanUsers(): HasMany
    {
        return $this->hasMany(MarzbanUser::class);
    }

    /**
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<User, User>
     */
    public function mergedIntoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_into_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isMerged(): bool
    {
        return $this->merged_into_user_id !== null;
    }

    public function hasTechnicalTelegramEmail(): bool
    {
        return str_starts_with($this->email, 'telegram-')
            && str_ends_with($this->email, '@telegram.local');
    }

    public function displayEmail(): ?string
    {
        return $this->hasTechnicalTelegramEmail() ? null : $this->email;
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
