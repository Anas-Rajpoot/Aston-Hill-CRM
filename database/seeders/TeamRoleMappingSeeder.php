<?php

namespace Database\Seeders;

use App\Models\TeamRoleMapping;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TeamRoleMappingSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['slot_key' => 'manager', 'role_name' => 'manager', 'sort_order' => 1],
            ['slot_key' => 'team_leader', 'role_name' => 'team_leader', 'sort_order' => 2],
            ['slot_key' => 'sales_agent', 'role_name' => 'sales_agent', 'sort_order' => 3],
        ];

        foreach ($slots as $slot) {
            $role = Role::where('name', $slot['role_name'])->first();
            if ($role) {
                TeamRoleMapping::updateOrCreate(
                    ['slot_key' => $slot['slot_key']],
                    ['role_id' => $role->id, 'sort_order' => $slot['sort_order']]
                );
            }
        }
    }
}
