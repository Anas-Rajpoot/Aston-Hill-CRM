<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Consolidate duplicate roles into a single 'web' guard.
 *
 * Problem:
 * - RoleSeeder created every role for BOTH 'web' and 'sanctum' guards,
 *   doubling the rows in the roles table.
 * - Naming inconsistencies: "Back Office" (web) vs "back_office" (sanctum),
 *   "support_manager1" (web) vs "support_manager" (sanctum).
 * - Users could have roles assigned under either guard, causing hasRole() mismatches.
 *
 * This migration:
 * 1. Fixes naming inconsistencies in web guard roles.
 * 2. Reassigns any user/model role links from sanctum roles to their web counterparts.
 * 3. Moves any role_has_permissions entries from sanctum roles to web roles.
 * 4. Deletes all sanctum guard roles.
 * 5. Clears Spatie permission cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─── Step 1: Fix naming inconsistencies in web guard roles ───────────
        // "Back Office" (id 5) should be "back_office"
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'Back Office')
            ->update(['name' => 'back_office']);

        // "support_manager1" (id 17) should be "support_manager"
        DB::table('roles')
            ->where('guard_name', 'web')
            ->where('name', 'support_manager1')
            ->update(['name' => 'support_manager']);

        // ─── Step 2: Build mapping from sanctum role IDs to web role IDs ─────
        $webRoles = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id', 'name'); // ['back_office' => 5, 'manager' => 2, ...]

        $sanctumRoles = DB::table('roles')
            ->where('guard_name', 'sanctum')
            ->get(['id', 'name']); // [{id: 6, name: 'back_office'}, ...]

        $sanctumToWeb = []; // sanctum_role_id => web_role_id
        $sanctumIds = [];

        foreach ($sanctumRoles as $sRole) {
            $sanctumIds[] = $sRole->id;
            if (isset($webRoles[$sRole->name])) {
                $sanctumToWeb[$sRole->id] = $webRoles[$sRole->name];
            }
        }

        // ─── Step 3: Reassign model_has_roles from sanctum to web ────────────
        // For each sanctum role, move user associations to the corresponding web role.
        // Skip duplicates (user already has the web role).
        foreach ($sanctumToWeb as $sanctumId => $webId) {
            // Get all model associations for this sanctum role
            $associations = DB::table('model_has_roles')
                ->where('role_id', $sanctumId)
                ->get();

            foreach ($associations as $assoc) {
                // Check if the user already has the web version of this role
                $exists = DB::table('model_has_roles')
                    ->where('role_id', $webId)
                    ->where('model_type', $assoc->model_type)
                    ->where('model_id', $assoc->model_id)
                    ->exists();

                if (!$exists) {
                    // Move association to web role
                    DB::table('model_has_roles')
                        ->where('role_id', $sanctumId)
                        ->where('model_type', $assoc->model_type)
                        ->where('model_id', $assoc->model_id)
                        ->update(['role_id' => $webId]);
                }
            }

            // Delete any remaining sanctum associations (duplicates that weren't moved)
            DB::table('model_has_roles')
                ->where('role_id', $sanctumId)
                ->delete();
        }

        // ─── Step 4: Reassign role_has_permissions from sanctum to web ───────
        foreach ($sanctumToWeb as $sanctumId => $webId) {
            $perms = DB::table('role_has_permissions')
                ->where('role_id', $sanctumId)
                ->pluck('permission_id');

            foreach ($perms as $permId) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $webId)
                    ->where('permission_id', $permId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $webId,
                        'permission_id' => $permId,
                    ]);
                }
            }

            DB::table('role_has_permissions')
                ->where('role_id', $sanctumId)
                ->delete();
        }

        // ─── Step 5: Delete all sanctum guard roles ──────────────────────────
        if (!empty($sanctumIds)) {
            DB::table('roles')->whereIn('id', $sanctumIds)->delete();
        }

        // Also delete any sanctum-only roles that had no web counterpart
        // (shouldn't exist, but safety net)
        DB::table('roles')->where('guard_name', 'sanctum')->delete();

        // ─── Step 6: Clean up orphaned model_has_roles entries ───────────────
        // Remove any entries pointing to non-existent roles
        $validRoleIds = DB::table('roles')->pluck('id');
        if ($validRoleIds->isNotEmpty()) {
            DB::table('model_has_roles')
                ->whereNotIn('role_id', $validRoleIds)
                ->delete();
        }

        // ─── Step 7: Clear Spatie permission cache ───────────────────────────
        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Safe to ignore during migration
        }
    }

    public function down(): void
    {
        // This migration is not safely reversible because we merge data.
        // To reverse, re-run the RoleSeeder with both guards.
    }
};
