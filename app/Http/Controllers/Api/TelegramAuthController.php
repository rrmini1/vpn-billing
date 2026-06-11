<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram\TelegramWebAppAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramAuthController extends Controller
{
    public function login(Request $request, TelegramWebAppAuthenticator $telegram): JsonResponse
    {
        $attributes = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $telegram->validate($attributes['init_data']);

        $user = User::query()
            ->where('telegram_id', $telegramUser['id'])
            ->first();

        if (! $user) {
            $user = User::query()->create([
                ...$this->userAttributes($telegramUser),
                'name' => $this->name($telegramUser),
                'email' => $this->telegramEmail($telegramUser['id']),
                'password' => Hash::make(Str::password(48)),
            ]);
        } else {
            $user->forceFill($this->userAttributes($telegramUser))->save();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user,
        ]);
    }

    public function link(Request $request, TelegramWebAppAuthenticator $telegram): JsonResponse
    {
        $attributes = $request->validate([
            'init_data' => ['required', 'string'],
        ]);

        $telegramUser = $telegram->validate($attributes['init_data']);

        $existingUser = User::query()
            ->where('telegram_id', $telegramUser['id'])
            ->whereKeyNot($request->user()->getKey())
            ->first();

        if ($existingUser) {
            throw ValidationException::withMessages([
                'init_data' => 'This Telegram account is already linked to another user.',
            ]);
        }

        $request->user()->forceFill($this->userAttributes($telegramUser))->save();

        return response()->json([
            'user' => $request->user()->refresh(),
        ]);
    }

    /**
     * @param  array{id: int, username?: string|null, first_name?: string|null, last_name?: string|null, photo_url?: string|null, auth_date: Carbon}  $telegramUser
     * @return array<string, mixed>
     */
    private function userAttributes(array $telegramUser): array
    {
        return [
            'telegram_id' => $telegramUser['id'],
            'telegram_username' => $telegramUser['username'] ?? null,
            'telegram_first_name' => $telegramUser['first_name'] ?? null,
            'telegram_last_name' => $telegramUser['last_name'] ?? null,
            'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
            'telegram_auth_date' => $telegramUser['auth_date'],
        ];
    }

    /**
     * @param  array{id: int, username?: string|null, first_name?: string|null, last_name?: string|null}  $telegramUser
     */
    private function name(array $telegramUser): string
    {
        $name = trim(implode(' ', array_filter([
            $telegramUser['first_name'] ?? null,
            $telegramUser['last_name'] ?? null,
        ])));

        if ($name !== '') {
            return $name;
        }

        if (! empty($telegramUser['username'])) {
            return '@'.$telegramUser['username'];
        }

        return 'Telegram user '.$telegramUser['id'];
    }

    private function telegramEmail(int $telegramId): string
    {
        return 'telegram-'.$telegramId.'@telegram.local';
    }
}
