<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users' => [
                    'total' => User::query()->count(),
                    'telegram_linked' => User::query()->whereNotNull('telegram_id')->count(),
                    'admins' => User::query()->where('role', User::ROLE_ADMIN)->count(),
                ],
                'subscriptions' => [
                    'active' => Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count(),
                    'total' => Subscription::query()->count(),
                ],
                'payments' => [
                    'total' => Payment::query()->count(),
                    'pending' => Payment::query()->where('status', Payment::STATUS_PENDING)->count(),
                    'paid' => Payment::query()->where('status', Payment::STATUS_PAID)->count(),
                    'failed' => Payment::query()->where('status', Payment::STATUS_FAILED)->count(),
                    'cancelled' => Payment::query()->where('status', Payment::STATUS_CANCELLED)->count(),
                    'revenue_amount' => (int) Payment::query()
                        ->where('status', Payment::STATUS_PAID)
                        ->sum('amount'),
                    'currency' => 'RUB',
                ],
                'plans' => [
                    'active' => Plan::query()->where('is_active', true)->count(),
                    'total' => Plan::query()->count(),
                ],
            ],
        ]);
    }
}
