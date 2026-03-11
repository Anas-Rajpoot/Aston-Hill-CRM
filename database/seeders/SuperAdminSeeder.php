<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    private const DEFAULT_SUPERADMIN_PASSWORD = 'Password@123';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ], [
            'status' => 'active',
        ]);

        $allPermissionNames = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();
        $role->syncPermissions($allPermissionNames);

        $user = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                // Must satisfy default password policy (min length + uppercase + number + special).
                'password' => bcrypt(self::DEFAULT_SUPERADMIN_PASSWORD),
                'status' => 'approved',
                // Default password is policy-compliant, so first login should not be forced to change it.
                'must_change_password' => false,
            ]
        );

        $user->syncRoles([$role->name]);
    }
}
