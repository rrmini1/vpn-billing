<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\MarzbanUser;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Marzban\MarzbanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->withCount(['payments', 'subscriptions'])
            ->with([
                'subscriptions' => fn ($query) => $query
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->with('marzbanUser')
                    ->latest('id'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('email', 'ilike', '%'.$search.'%')
                        ->orWhere('telegram_username', 'ilike', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $query->orWhere('telegram_id', (int) $search);
                    }
                });
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return AdminUserResource::collection($users);
    }

    public function updateMarzbanLimit(Request $request, User $user, MarzbanService $marzban): JsonResponse
    {
        $attributes = $request->validate([
            'data_limit_bytes' => ['required', 'integer', 'min:1'],
        ]);

        $marzbanUser = $this->activeMarzbanUser($user);

        if (! $marzbanUser) {
            throw ValidationException::withMessages([
                'user' => 'User does not have an active Marzban access.',
            ]);
        }

        $response = $marzban->updateUserLimit(
            $marzbanUser->username,
            (int) $attributes['data_limit_bytes'],
        );

        $marzbanUser->forceFill([
            'status' => $response['status'] ?? MarzbanUser::STATUS_ACTIVE,
            'data_limit_bytes' => $response['data_limit'] ?? $attributes['data_limit_bytes'],
            'subscription_url' => $response['subscription_url'] ?? $marzbanUser->getRawOriginal('subscription_url'),
            'raw_response' => $response,
        ])->save();

        $user->loadCount(['payments', 'subscriptions']);
        $user->load([
            'subscriptions' => fn ($query) => $query
                ->where('status', Subscription::STATUS_ACTIVE)
                ->with('marzbanUser')
                ->latest('id'),
        ]);

        return response()->json([
            'data' => new AdminUserResource($user),
        ]);
    }

    private function activeMarzbanUser(User $user): ?MarzbanUser
    {
        $subscription = $user
            ->subscriptions()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('marzbanUser')
            ->latest('id')
            ->first();

        return $subscription?->marzbanUser;
    }
}
