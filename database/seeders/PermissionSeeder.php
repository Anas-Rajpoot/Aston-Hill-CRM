<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        $modules = config('permissions.modules');
        $actions = array_keys(config('permissions.actions'));

        foreach ($modules as $moduleKey => $label) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }
    }
}
