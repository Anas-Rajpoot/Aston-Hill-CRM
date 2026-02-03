<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add index on role_has_permissions.role_id for fast "permissions for role" lookups.
 * Primary is (permission_id, role_id); lookups by role_id benefit from this index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->index('role_id', 'role_has_permissions_role_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropIndex('role_has_permissions_role_id_index');
        });
    }
};
