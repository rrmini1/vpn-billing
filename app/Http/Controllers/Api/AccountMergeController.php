<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AttachEmailConfirmationMail;
use App\Models\AccountMergeToken;
use App\Models\EmailAttachToken;
use App\Models\User;
use App\Notifications\AccountMergeConfirmationNotification;
use App\Services\Accounts\AccountMergeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AccountMergeController extends Controller
{
    public function startEmailFlow(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
        ]);

        /** @var User $source */
        $source = $request->user();
        $this->ensureTelegramOnlySource($source);

        $target = User::query()
            ->where('email', $attributes['email'])
            ->first();

        if (! $target) {
            [$attachToken, $plainToken] = EmailAttachToken::issue($source, $attributes['email']);

            Mail::to($attributes['email'])
                ->send(new AttachEmailConfirmationMail($plainToken, $this->requestLocale($request)));

            return response()->json([
                'status' => 'email_attach_confirmation_sent',
                'expires_at' => $attachToken->expires_at?->toISOString(),
            ], 202);
        }

        return $this->sendMergeConfirmation($source, $target);
    }

    public function startEmailMerge(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
        ]);

        /** @var User $source */
        $source = $request->user();
        $this->ensureTelegramOnlySource($source);

        $target = User::query()
            ->where('email', $attributes['email'])
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'email' => 'Email account was not found.',
            ]);
        }

        return $this->sendMergeConfirmation($source, $target);
    }

    public function completeEmailAttach(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $attachToken = EmailAttachToken::query()
            ->with('user')
            ->where('token_hash', EmailAttachToken::hashToken($attributes['token']))
            ->first();

        if (! $attachToken || ! $attachToken->isConfirmable()) {
            throw ValidationException::withMessages([
                'token' => 'Email attach token is invalid.',
            ]);
        }

        if (User::query()->where('email', $attachToken->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        /** @var User $user */
        $user = $attachToken->user;
        $this->ensureTelegramOnlySource($user);

        $user->forceFill([
            'email' => $attachToken->email,
            'email_verified_at' => now(),
            'password' => Hash::make($attributes['password']),
        ])->save();

        $attachToken->markConfirmed();

        return response()->json([
            'status' => 'email_attached',
        ]);
    }

    private function sendMergeConfirmation(User $source, User $target): JsonResponse
    {
        if (! $target->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Email exists but is not verified. Please verify this email before merging accounts.',
            ]);
        }

        if ($target->isMerged()) {
            throw ValidationException::withMessages([
                'email' => 'This email account was already merged into another account.',
            ]);
        }

        if ($target->telegram_id && $target->telegram_id !== $source->telegram_id) {
            throw ValidationException::withMessages([
                'email' => 'This email account is already linked to another Telegram account.',
            ]);
        }

        [$mergeToken, $plainToken] = AccountMergeToken::issue($source, $target);

        $target->notify(new AccountMergeConfirmationNotification($plainToken));

        return response()->json([
            'status' => 'merge_confirmation_sent',
            'expires_at' => $mergeToken->expires_at?->toISOString(),
        ], 202);
    }

    public function confirmEmailMerge(Request $request, AccountMergeService $mergeService): JsonResponse|RedirectResponse
    {
        $plainToken = (string) $request->query('token', '');
        $mergeToken = AccountMergeToken::query()
            ->with(['sourceUser', 'targetUser'])
            ->where('token_hash', AccountMergeToken::hashToken($plainToken))
            ->first();

        if (! $mergeToken || ! $mergeToken->isConfirmable()) {
            return $this->mergeResponse($request, 'invalid');
        }

        try {
            $mergeService->mergeTelegramAccountIntoVerifiedEmailAccount(
                $mergeToken->sourceUser,
                $mergeToken->targetUser,
            );
        } catch (DomainException) {
            return $this->mergeResponse($request, 'invalid');
        }

        $mergeToken->markConfirmed();

        return $this->mergeResponse($request, 'success');
    }

    private function mergeResponse(Request $request, string $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            if ($status === 'success') {
                return response()->json(['status' => 'merged']);
            }

            return response()->json(['message' => 'Merge confirmation token is invalid.'], 422);
        }

        return redirect()->to(
            rtrim((string) config('app.frontend_url'), '/').'/app/account-merged?status='.$status,
        );
    }

    private function ensureTelegramOnlySource(User $source): void
    {
        if (! $source->telegram_id || ! $source->hasTechnicalTelegramEmail()) {
            throw ValidationException::withMessages([
                'account' => 'Only Telegram-only accounts can start this flow.',
            ]);
        }

        if ($source->isMerged()) {
            throw ValidationException::withMessages([
                'account' => 'Merged accounts cannot start account merge.',
            ]);
        }
    }

    private function requestLocale(Request $request): string
    {
        return $request->header('X-Locale') === 'en' ? 'en' : 'ru';
    }
}
