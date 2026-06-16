<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_api(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_admin_api(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_can_fetch_dashboard_stats(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['telegram_id' => 123456]);
        $plan = Plan::query()->where('code', 'start')->firstOrFail();

        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'price_amount' => 10000,
            'currency' => 'RUB',
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
        Payment::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => 'start',
            'plan_name' => 'Старт',
            'traffic_limit_bytes' => 53687091200,
            'amount' => 10000,
            'currency' => 'RUB',
            'status' => Payment::STATUS_PAID,
            'provider' => 'mock',
            'paid_at' => now(),
        ]);

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.total', 2)
            ->assertJsonPath('data.users.telegram_linked', 1)
            ->assertJsonPath('data.users.admins', 1)
            ->assertJsonPath('data.subscriptions.active', 1)
            ->assertJsonPath('data.payments.paid', 1)
            ->assertJsonPath('data.payments.revenue_amount', 10000);
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create([
            'name' => 'Roman Client',
            'email' => 'client@example.com',
            'telegram_username' => 'client_tg',
        ]);

        $this
            ->actingAs($admin)
            ->getJson('/api/admin/users?search=client')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'client@example.com')
            ->assertJsonPath('data.0.telegram.username', 'client_tg');
    }

    public function test_admin_can_update_plan(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $plan = Plan::query()->where('code', 'start')->firstOrFail();

        $this
            ->actingAs($admin)
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->patchJson('/api/admin/plans/'.$plan->id, [
                'name' => 'Старт Plus',
                'traffic_limit_bytes' => 75 * 1024 * 1024 * 1024,
                'price_amount' => 29900,
                'is_active' => true,
                'sort_order' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Старт Plus')
            ->assertJsonPath('data.price_amount', 29900);

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Старт Plus',
            'price_amount' => 29900,
        ]);
    }
}
