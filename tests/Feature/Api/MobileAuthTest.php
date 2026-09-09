<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_user_can_register_restore_session_and_logout(): void
    {
        $registration = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Ada',
            'last_name' => 'Okafor',
            'email' => 'ada@example.com',
            'phone' => '+2348012345678',
            'currency_code' => 'NGN',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'terms' => true,
        ])->assertCreated()
            ->assertJsonPath('data.user.email', 'ada@example.com');

        $token = $registration->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Ada Okafor');

        $this->assertDatabaseHas(Customer::class, ['email' => 'ada@example.com']);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_mobile_user_can_login_and_invalid_credentials_are_friendly(): void
    {
        User::factory()->create([
            'email' => 'traveller@example.com',
            'password' => 'Password123',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'traveller@example.com',
            'password' => 'Password123',
        ])->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'traveller@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email address or password is incorrect.');
    }
}
