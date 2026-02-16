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

        // 1) Module × Action matrix permissions (e.g. users.create, accounts.delete)
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

        // 2) Structure-based granular permissions (e.g. notification_rules.manage_templates)
        $structure = config('permissions.structure', []);

        foreach ($structure as $moduleKey => $module) {
            foreach ($module['permissions'] ?? [] as $perm) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$perm['key']}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // 3) Legacy standalone permissions (kept for backward compatibility)
        $standalone = [
            'manage-notification-rules',
            'manage-escalation-levels',
            'manage-system-preferences',
            'manage-sla',
            'manage-security-settings',
            'manage-announcements',
            'manage-library',
            'view-library',
            'download-library',
            'export-audit-logs',
        ];

        foreach ($standalone as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }
    }
}
