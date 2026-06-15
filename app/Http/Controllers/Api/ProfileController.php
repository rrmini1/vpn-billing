<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Subscription;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        $user = $request->user()->load([
            'subscriptions' => fn ($query) => $query
                ->where('status', Subscription::STATUS_ACTIVE)
                ->with('marzbanUser')
                ->latest('id')
                ->limit(1),
        ]);

        return new ProfileResource($user);
    }
}
