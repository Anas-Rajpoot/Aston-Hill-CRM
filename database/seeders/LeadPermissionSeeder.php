<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class LeadPermissionSeeder extends Seeder
{
    /**
     * Lead module permissions: lead.view, lead.create, lead.edit, lead.delete
     * Scoped view: lead.view.all, lead.view.assigned, lead.view.created
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'lead.view',
            'lead.create',
            'lead.edit',
            'lead.delete',
            'lead.view.all',      // See all leads
            'lead.view.assigned', // Only leads assigned (sales_agent, team_leader, manager)
            'lead.view.created',  // Only leads created by user
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }
}
