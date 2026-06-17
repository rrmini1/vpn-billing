<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TrafficController;
use Illuminate\Support\Facades\Route;

Route::get('plans', [PlanController::class, 'index']);

Route::middleware('auth:web')->get('profile', [ProfileController::class, 'show']);
Route::middleware('auth:web')->get('traffic', [TrafficController::class, 'show']);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('telegram/login', [TelegramAuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:web')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('telegram/link', [TelegramAuthController::class, 'link']);
    });
});

Route::middleware('auth:web')->prefix('email')->group(function (): void {
    Route::post('verification-notification', [AuthController::class, 'sendEmailVerificationNotification'])
        ->middleware('throttle:6,1');
});

Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth:web')->prefix('subscriptions')->group(function (): void {
    Route::get('current', [SubscriptionController::class, 'current']);
    Route::post('checkout', [SubscriptionController::class, 'checkout']);
    Route::post('trial', [SubscriptionController::class, 'trial']);
});

Route::middleware('auth:web')->prefix('payments')->group(function (): void {
    Route::get('/', [PaymentController::class, 'index']);
    Route::post('/', [PaymentController::class, 'store']);
    Route::post('{payment}/simulate-paid', [PaymentController::class, 'simulatePaid']);
});

Route::middleware(['auth:web', 'admin'])->prefix('admin')->group(function (): void {
    Route::get('dashboard', [AdminDashboardController::class, 'show']);
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('payments', [AdminPaymentController::class, 'index']);
    Route::get('plans', [AdminPlanController::class, 'index']);
    Route::post('plans', [AdminPlanController::class, 'store']);
    Route::patch('plans/{plan}', [AdminPlanController::class, 'update']);
});

Route::prefix('webhooks/payments')->group(function (): void {
    Route::post('mock', [PaymentWebhookController::class, 'handleMock']);
    Route::post('yookassa', [PaymentWebhookController::class, 'handleYooKassa']);
});
