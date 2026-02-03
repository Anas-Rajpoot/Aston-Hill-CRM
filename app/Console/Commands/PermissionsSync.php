<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync permissions from config/permissions.php structure to the database.';

    public function handle(): int
    {
        $structure = config('permissions.structure', []);
        $guard = 'web';
        $created = 0;

        foreach ($structure as $moduleKey => $moduleDef) {
            foreach ($moduleDef['permissions'] ?? [] as $permDef) {
                $key = $permDef['key'] ?? '';
                $name = $moduleKey . '.' . $key;
                $exists = Permission::where('name', $name)->where('guard_name', $guard)->exists();
                if (!$exists) {
                    Permission::create(['name' => $name, 'guard_name' => $guard]);
                    $created++;
                    $this->line("Created: {$name}");
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info("Done. Created {$created} new permission(s).");
        return self::SUCCESS;
    }
}
