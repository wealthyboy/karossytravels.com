<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminCustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_view_update_and_delete_a_customer(): void
    {
        $admin = $this->authorizedAdmin();
        $create = $this->actingAs($admin)->post('/admin/customers', [
            'title' => 'Mr',
            'first_name' => 'Jacob',
            'last_name' => 'Atam',
            'email' => 'jacob.customer@example.test',
            'phone' => '+2348000000000',
            'date_of_birth' => '1990-04-10',
            'gender' => 'male',
            'nationality' => 'ng',
            'country' => 'ng',
            'status' => 'active',
            'passport_number' => 'A12345678',
            'passport_country' => 'ng',
            'passport_expires_at' => now()->addYears(3)->toDateString(),
        ]);

        $customer = Customer::firstOrFail();
        $create->assertRedirect(route('admin.customers.show', $customer));
        $this->assertSame('NG', $customer->nationality);
        $this->assertSame('A12345678', $customer->passport_number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.created', 'subject_id' => $customer->id]);

        $this->actingAs($admin)->get(route('admin.customers.show', $customer))->assertOk()->assertSee('Jacob Atam');

        $update = $this->actingAs($admin)->put(route('admin.customers.update', $customer), [
            'title' => 'Mr',
            'first_name' => 'Jacob',
            'last_name' => 'Atam',
            'email' => 'jacob.customer@example.test',
            'phone' => '+2348000000000',
            'date_of_birth' => '1990-04-10',
            'gender' => 'male',
            'nationality' => 'NG',
            'country' => 'NG',
            'company_name' => 'Karossy Partner',
            'status' => 'active',
            'passport_country' => 'NG',
            'passport_expires_at' => now()->addYears(3)->toDateString(),
        ]);
        $update->assertRedirect(route('admin.customers.show', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'company_name' => 'Karossy Partner']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'subject_id' => $customer->id]);

        $this->actingAs($admin)->delete(route('admin.customers.destroy', $customer))->assertRedirect(route('admin.customers.index'));
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.deleted', 'subject_id' => $customer->id]);
    }

    public function test_customer_list_is_searchable_and_sortable(): void
    {
        Customer::create(['first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ada@example.test', 'status' => 'active']);
        Customer::create(['first_name' => 'Tunde', 'last_name' => 'Bello', 'email' => 'tunde@example.test', 'company_name' => 'Travel Co', 'status' => 'pending']);

        $this->actingAs($this->authorizedAdmin())->get('/admin/customers?q=Travel&sort=first_name&direction=asc')
            ->assertOk()->assertSee('Tunde Bello')->assertDontSee('Ada Okafor');
    }

    public function test_bulk_delete_skips_customers_with_booking_history(): void
    {
        $admin = $this->authorizedAdmin();
        $deletable = Customer::create(['first_name' => 'Delete', 'last_name' => 'Me', 'email' => 'delete@example.test', 'status' => 'active']);
        $protected = Customer::create(['first_name' => 'Keep', 'last_name' => 'Me', 'email' => 'keep@example.test', 'status' => 'active']);
        Order::create([
            'reference' => 'KAR-TEST-001', 'customer_id' => $protected->id, 'channel' => 'admin',
            'status' => 'draft', 'currency' => 'NGN', 'subtotal_minor' => 0,
            'fees_minor' => 0, 'discount_minor' => 0, 'total_minor' => 0,
        ]);

        $this->actingAs($admin)->delete(route('admin.customers.bulk-destroy'), ['ids' => [$deletable->id, $protected->id]])
            ->assertSessionHas('success');

        $this->assertSoftDeleted('customers', ['id' => $deletable->id]);
        $this->assertDatabaseHas('customers', ['id' => $protected->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.bulk_deleted']);
    }

    private function authorizedAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'customer-admin-'.uniqid(), 'label' => 'Customer Administrator']);
        $permissions = collect(['customers.view' => 'View Customers', 'customers.manage' => 'Manage Customers'])
            ->map(fn (string $label, string $name) => Permission::firstOrCreate(['name' => $name], ['label' => $label]));
        $role->permissions()->attach($permissions);
        $user->roles()->attach($role);

        return $user;
    }
}
