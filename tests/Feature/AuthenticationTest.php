<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_a_linked_b2c_customer_account(): void
    {
        $this->post('/register', [
            'first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada@example.com',
            'phone' => '+2348000000000', 'currency_code' => 'NGN', 'password' => 'Password123',
            'password_confirmation' => 'Password123', 'terms' => '1',
        ])->assertRedirect('/');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('customers', ['user_id' => $user->id, 'email' => 'ada@example.com']);
    }

    public function test_active_admin_returns_to_website_and_sees_admin_link_after_login(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['account_type' => 'admin', 'status' => 'active', 'password' => 'Password123']);
        $admin->roles()->attach(Role::where('name', 'super-admin')->firstOrFail());

        $this->post('/login', ['email' => $admin->email, 'password' => 'Password123'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
        $this->get('/')->assertOk()->assertSee('Go to admin');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => 'Password123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_routes_are_hidden_from_guests(): void
    {
        $this->get('/admin')->assertNotFound()->assertSee('We couldn’t find that page.');
        $this->get('/admin/customers')->assertNotFound()->assertSee('Back to homepage');
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
