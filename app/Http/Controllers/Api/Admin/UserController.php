<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
