<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountMergeToken;
use App\Models\User;
use App\Notifications\AccountMergeConfirmationNotification;
use App\Services\Accounts\AccountMergeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountMergeController extends Controller
{
    public function startEmailMerge(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email'],
        ]);

        /** @var User $source */
        $source = $request->user();

        if (! $source->telegram_id || ! $source->hasTechnicalTelegramEmail()) {
            throw ValidationException::withMessages([
                'account' => 'Only Telegram-only accounts can start this merge flow.',
            ]);
        }

        if ($source->isMerged()) {
            throw ValidationException::withMessages([
                'account' => 'Merged accounts cannot start account merge.',
            ]);
        }

        $target = User::query()
            ->where('email', $attributes['email'])
            ->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'email' => 'Email account was not found.',
            ]);
        }

        if (! $target->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Email exists but is not verified. Merge is not available.',
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
}
