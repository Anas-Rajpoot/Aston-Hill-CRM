<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure back_office role exists for both web and sanctum guards.
     * Fixes: "There is no role named `backoffice` for guard `sanctum`."
     * Code now uses only the role name "back_office".
     */
    public function up(): void
    {
        foreach (['web', 'sanctum'] as $guard) {
            Role::firstOrCreate(
                ['name' => 'back_office', 'guard_name' => $guard],
                ['name' => 'back_office', 'guard_name' => $guard]
            );
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Role::where('name', 'back_office')->whereIn('guard_name', ['web', 'sanctum'])->delete();
    }
};
