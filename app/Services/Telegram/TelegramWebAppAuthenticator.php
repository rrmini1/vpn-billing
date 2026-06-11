<?php

namespace App\Services\Telegram;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TelegramWebAppAuthenticator
{
    /**
     * @return array{
     *     id: int,
     *     username?: string|null,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     photo_url?: string|null,
     *     auth_date: Carbon
     * }
     */
    public function validate(string $initData): array
    {
        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            throw ValidationException::withMessages([
                'init_data' => 'Invalid Telegram authentication data.',
            ]);
        }

        unset($data['hash']);
        ksort($data);

        $checkString = collect($data)
            ->map(fn (mixed $value, string $key): string => $key.'='.$value)
            ->implode("\n");

        $botToken = config('telegram.bot_token');

        if (! is_string($botToken) || $botToken === '') {
            throw ValidationException::withMessages([
                'init_data' => 'Telegram authentication is not configured.',
            ]);
        }

        $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $checkString, $secret);

        if (! hash_equals($calculatedHash, $hash)) {
            throw ValidationException::withMessages([
                'init_data' => 'Invalid Telegram authentication data.',
            ]);
        }

        $authDate = $this->authDate($data['auth_date'] ?? null);
        $maxAge = (int) config('telegram.auth_max_age', 86400);

        if ($authDate->lt(now()->subSeconds($maxAge))) {
            throw ValidationException::withMessages([
                'init_data' => 'Telegram authentication data has expired.',
            ]);
        }

        $user = $this->userData($data['user'] ?? null);

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'photo_url' => $user['photo_url'] ?? null,
            'auth_date' => $authDate,
        ];
    }

    private function authDate(mixed $value): Carbon
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'init_data' => 'Invalid Telegram authentication data.',
            ]);
        }

        return Carbon::createFromTimestamp((int) $value);
    }

    /**
     * @return array{id: int|string, username?: string|null, first_name?: string|null, last_name?: string|null, photo_url?: string|null}
     */
    private function userData(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([
                'init_data' => 'Invalid Telegram user data.',
            ]);
        }

        $user = json_decode($value, true);

        if (! is_array($user) || ! isset($user['id']) || ! is_numeric($user['id'])) {
            throw ValidationException::withMessages([
                'init_data' => 'Invalid Telegram user data.',
            ]);
        }

        return $user;
    }
}
