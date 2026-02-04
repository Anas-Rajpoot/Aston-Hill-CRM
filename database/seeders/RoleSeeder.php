<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'superadmin',
            'manager',
            'team_leader',
            'sales_agent',
            'back_office',
        ];

        $guards = ['web', 'sanctum'];

        foreach ($roles as $role) {
            foreach ($guards as $guard) {
                Role::firstOrCreate([
                    'name' => $role,
                    'guard_name' => $guard,
                ]);
            }
        }
    }
}
