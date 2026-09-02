<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(AdminPermission::cases())->mapWithKeys(function (AdminPermission $permission): array {
            $model = Permission::updateOrCreate(
                ['name' => $permission->value],
                ['label' => str($permission->value)->replace('.', ' ')->headline()],
            );

            return [$permission->value => $model->id];
        });

        $superAdmin = Role::updateOrCreate(['name' => 'super-admin'], ['label' => 'Super Administrator']);
        $superAdmin->permissions()->sync($permissions->values());

        $operations = Role::updateOrCreate(['name' => 'operations'], ['label' => 'Operations']);
        $operations->permissions()->sync($permissions->only([
            AdminPermission::ViewDashboard->value,
            AdminPermission::ViewBookings->value,
            AdminPermission::ManageBookings->value,
            AdminPermission::ViewCustomers->value,
            AdminPermission::ManageCustomers->value,
            AdminPermission::ViewOffers->value,
            AdminPermission::ManageOffers->value,
            AdminPermission::ViewServices->value,
        ])->values());

        $analyst = Role::updateOrCreate(['name' => 'analyst'], ['label' => 'Analyst']);
        $analyst->permissions()->sync($permissions->only([
            AdminPermission::ViewDashboard->value,
            AdminPermission::ViewAnalytics->value,
        ])->values());

        $b2bAgent = Role::updateOrCreate(['name' => 'b2b-agent'], ['label' => 'B2B Travel Agent']);
        $b2bAgent->permissions()->sync($permissions->only([
            AdminPermission::ViewDashboard->value,
            AdminPermission::ViewBookings->value,
            AdminPermission::ManageBookings->value,
            AdminPermission::ViewCustomers->value,
            AdminPermission::ManageCustomers->value,
            AdminPermission::ViewOffers->value,
            AdminPermission::ViewServices->value,
        ])->values());
    }
}
