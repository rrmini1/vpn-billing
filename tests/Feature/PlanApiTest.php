<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_seeder_creates_default_plans(): void
    {
        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas('plans', [
            'code' => 'trial',
            'name' => 'Тест',
            'traffic_limit_bytes' => 1073741824,
            'price_amount' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('plans', [
            'code' => 'start',
            'traffic_limit_bytes' => 53687091200,
        ]);
        $this->assertDatabaseHas('plans', [
            'code' => 'standard',
            'traffic_limit_bytes' => 161061273600,
        ]);
        $this->assertDatabaseHas('plans', [
            'code' => 'premium',
            'traffic_limit_bytes' => 536870912000,
        ]);
    }

    public function test_plans_api_returns_active_plans_in_sort_order(): void
    {
        Plan::query()->create([
            'code' => 'hidden',
            'name' => 'Hidden',
            'traffic_limit_bytes' => 1024,
            'price_amount' => 0,
            'currency' => 'RUB',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $this->seed(PlanSeeder::class);

        $response = $this->getJson('/api/plans');

        $response
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.code', 'trial')
            ->assertJsonPath('data.1.code', 'start')
            ->assertJsonPath('data.2.code', 'standard')
            ->assertJsonPath('data.3.code', 'premium')
            ->assertJsonPath('data.0.traffic_limit_bytes', 1073741824)
            ->assertJsonMissingPath('data.4');
    }
}
