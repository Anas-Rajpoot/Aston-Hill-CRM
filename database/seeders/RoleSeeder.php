<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * All roles from the Role Assignment UI (except Super Admin) with descriptions.
     * Superadmin is created without description so it exists but is not shown in assignable list.
     */
    public function run(): void
    {
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

        $guards = ['web', 'sanctum'];

        foreach ($roles as $role) {
            $name = $role['name'];
            $description = $role['description'] ?? null;
            foreach ($guards as $guard) {
                Role::firstOrCreate(
                    [
                        'name' => $name,
                        'guard_name' => $guard,
                    ],
                    [
                        'description' => $description,
                        'status' => 'active',
                    ]
                );
            }
            // Update description for existing roles (firstOrCreate only sets it on create)
            if ($description !== null) {
                Role::where('name', $name)->update(['description' => $description]);
            }
        }
    }
}
