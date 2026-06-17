<?php

namespace App\Providers;

use App\Services\Payments\MockPaymentProvider;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\YooKassaPaymentProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, function () {
            return match (config('payments.default')) {
                'yookassa' => $this->app->make(YooKassaPaymentProvider::class),
                default => $this->app->make(MockPaymentProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
