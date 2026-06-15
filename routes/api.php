<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/payments')->group(function (): void {
    Route::post('mock', [PaymentWebhookController::class, 'handleMock']);
});
