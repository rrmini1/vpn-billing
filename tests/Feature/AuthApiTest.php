<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_authenticated(): void
    {
        $response = $this->postJsonWithCsrf('/api/auth/register', [
            'name' => 'Roman',
            'email' => 'roman@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'roman@example.com')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'roman@example.com']);
    }

    public function test_register_requires_unique_email(): void
    {
        User::factory()->create(['email' => 'roman@example.com']);

        $response = $this->postJsonWithCsrf('/api/auth/register', [
            'name' => 'Roman',
            'email' => 'roman@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_login_and_fetch_current_user(): void
    {
        $user = User::factory()->create([
            'email' => 'roman@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJsonWithCsrf('/api/auth/login', [
            'email' => 'roman@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'roman@example.com')
            ->assertJsonMissingPath('user.password');
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'roman@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJsonWithCsrf('/api/auth/login', [
            'email' => 'roman@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_fetch_current_user(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'roman@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postJsonWithCsrf('/api/auth/login', [
            'email' => 'roman@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->postJsonWithCsrf('/api/auth/logout')
            ->assertNoContent();

        $this->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    private function postJsonWithCsrf(string $uri, array $data = []): TestResponse
    {
        return $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson($uri, $data);
    }
}
