<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * All roles use the 'web' guard exclusively.
     * Sanctum uses stateful session auth (web guard), so a separate
     * 'sanctum' guard for roles is unnecessary and causes duplication.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'superadmin',
                'description' => null,
            ],
            [
                'name' => 'manager',
                'description' => 'Manages sales team, lead submissions, and client relationships. Can view reports and assign leads.',
            ],
            [
                'name' => 'team_leader',
                'description' => 'Leads a team of sales representatives. Can manage team submissions and view team performance.',
            ],
            [
                'name' => 'back_office',
                'description' => 'Processes and verifies submissions from the back office queue. Handles documentation and compliance.',
            ],
            [
                'name' => 'sales_agent',
                'description' => 'Conducts field visits and submits field operation reports. Mobile-first role for on-ground activities.',
            ],
            [
                'name' => 'field_agent',
                'description' => 'Conducts field visits and submits field operation reports. Mobile-first role for on-ground activities.',
            ],
            [
                'name' => 'field_operations_head',
                'description' => 'Oversees all field operations and agents. Reviews field submissions and manages field team performance.',
            ],
            [
                'name' => 'customer_support_representative',
                'description' => 'Handles customer inquiries, complaints, and support tickets. First point of contact for customers.',
            ],
            [
                'name' => 'support_manager',
                'description' => 'Manages customer support team and escalated issues. Oversees support operations and quality.',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                [
                    'name' => $role['name'],
                    'guard_name' => 'web',
                ],
                [
                    'description' => $role['description'],
                    'status' => 'active',
                ]
            );

            // Update description for existing roles (firstOrCreate only sets on create)
            if ($role['description'] !== null) {
                Role::where('name', $role['name'])
                    ->where('guard_name', 'web')
                    ->update(['description' => $role['description']]);
            }
        }

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $roleBundles = [
            'superadmin' => $allPermissions,
            'manager' => $this->bundleForModules(['lead', 'lead-submissions', 'field-submissions', 'customer_support_requests', 'vas_requests', 'clients', 'teams', 'reports']),
            'team_leader' => $this->bundleForModules(['lead', 'lead-submissions', 'field-submissions', 'customer_support_requests', 'vas_requests', 'clients']),
            'back_office' => $this->bundleForModules(['lead-submissions', 'field-submissions', 'customer_support_requests', 'vas_requests', 'special_requests']),
            'sales_agent' => $this->bundleForModules(['lead', 'lead-submissions', 'field-submissions', 'customer_support_requests', 'vas_requests', 'special_requests']),
            'field_agent' => $this->bundleForModules(['field-submissions', 'lead-submissions']),
            'field_operations_head' => $this->bundleForModules(['field-submissions', 'lead-submissions', 'reports']),
            'customer_support_representative' => $this->bundleForModules(['customer_support_requests', 'clients']),
            'support_manager' => $this->bundleForModules(['customer_support_requests', 'clients', 'reports']),
        ];

        foreach ($roleBundles as $roleName => $permissionNames) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $validNames = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', array_values(array_unique($permissionNames)))
                ->pluck('name')
                ->all();

            $role->syncPermissions($validNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Build a deterministic baseline bundle: read/create/update for selected modules.
     *
     * @param array<int,string> $modules
     * @return array<int,string>
     */
    private function bundleForModules(array $modules): array
    {
        $actions = ['read', 'create', 'update'];
        $names = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return array_values(array_unique($names));
    }
}
