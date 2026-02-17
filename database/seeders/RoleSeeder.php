<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
