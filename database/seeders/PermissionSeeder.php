<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // 0) Reset permission matrix (preserve roles/users, refresh role<->permission links)
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_permissions')->delete();
        if (Schema::hasTable('role_inheritance')) {
            DB::table('role_inheritance')->delete();
        }
        Permission::query()->where('guard_name', 'web')->delete();

        // 1) Canonical module × action matrix permissions.
        $modules = $this->resolveModuleKeys();
        $actions = config('permissions.canonical_actions', ['create', 'read', 'update', 'delete', 'assign_permissions']);

        foreach ($modules as $moduleKey => $label) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // 2) Keep existing granular module-specific permissions (backward compatibility)
        $structure = config('permissions.structure', []);

        foreach ($structure as $moduleKey => $module) {
            foreach ($module['permissions'] ?? [] as $perm) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$perm['key']}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // 3) Legacy matrix-style permissions (list/view/edit variants)
        $legacyModules = config('permissions.modules', []);
        $legacyActions = array_keys(config('permissions.actions', []));
        foreach ($legacyModules as $moduleKey => $label) {
            foreach ($legacyActions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$moduleKey}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // 4) Legacy standalone permissions (kept for backward compatibility)
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Build module list for canonical CRUD+assign_permissions seeding.
     *
     * Uses:
     * - config/permissions.php structure keys
     * - explicit aliases currently used in policies/controllers
     *
     * @return array<string,string>
     */
    private function resolveModuleKeys(): array
    {
        $fromStructure = array_keys(config('permissions.structure', []));
        $fromLegacyModules = array_keys(config('permissions.modules', []));
        $explicit = [
            'roles',
            'users',
            'teams',
            'accounts',
            'clients',
            'all_clients',
            'lead',
            'lead-submissions',
            'field-submissions',
            'customer_support_requests',
            'vas_requests',
            'vas',
            'special_requests',
            'dsp_tracker',
            'dsp_tracker_status',
            'gsm_verifiers',
            'verifiers',
            'extensions',
            'emails_followup',
            'expense_tracker',
            'attendance',
            'reports',
            'settings',
            'notification_rules',
            'announcements',
            'order_status',
            'employees',
            'email_follow_ups',
            'cisco_extensions',
        ];

        $merged = array_values(array_unique(array_merge($fromStructure, $fromLegacyModules, $explicit)));
        $result = [];
        foreach ($merged as $moduleKey) {
            $result[$moduleKey] = ucfirst(str_replace(['-', '_'], ' ', $moduleKey));
        }

        return $result;
    }
}
