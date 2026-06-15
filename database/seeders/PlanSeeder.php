<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the application's plans.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'trial',
                'name' => 'Тест',
                'traffic_limit_bytes' => 1 * 1024 * 1024 * 1024,
                'price_amount' => 0,
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'start',
                'name' => 'Старт',
                'traffic_limit_bytes' => 50 * 1024 * 1024 * 1024,
                'price_amount' => 0,
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'standard',
                'name' => 'Стандарт',
                'traffic_limit_bytes' => 150 * 1024 * 1024 * 1024,
                'price_amount' => 0,
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'premium',
                'name' => 'Премиум',
                'traffic_limit_bytes' => 500 * 1024 * 1024 * 1024,
                'price_amount' => 0,
                'currency' => 'RUB',
                'is_active' => true,
                'sort_order' => 40,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }
}
