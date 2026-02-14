<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Refactor escalation_levels:
 * - recipient_type changes from enum('assignee','manager','admin','custom_user','custom_email')
 *   to a VARCHAR that stores system role slugs (e.g. 'superadmin', 'back_office', etc.).
 * - custom_email column is repurposed as the email address for escalation notifications.
 * - email_override column is removed (no longer needed).
 * - custom_user_id column is removed (no longer needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop foreign key on custom_user_id first
        Schema::table('escalation_levels', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['custom_user_id']);
        });

        // 2) Alter columns
        Schema::table('escalation_levels', function (Blueprint $table) {
            // Change recipient_type from ENUM to VARCHAR to hold role slugs
            $table->string('recipient_type', 100)->default('superadmin')->change();

            // Drop columns no longer needed
            $table->dropColumn(['custom_user_id', 'email_override']);
        });

        // 3) Migrate existing data: map old enum values to role slugs
        DB::table('escalation_levels')
            ->where('recipient_type', 'assignee')
            ->update(['recipient_type' => 'sales_agent']);

        DB::table('escalation_levels')
            ->where('recipient_type', 'manager')
            ->update(['recipient_type' => 'manager']);

        DB::table('escalation_levels')
            ->where('recipient_type', 'admin')
            ->update(['recipient_type' => 'superadmin']);

        DB::table('escalation_levels')
            ->whereIn('recipient_type', ['custom_user', 'custom_email'])
            ->update(['recipient_type' => 'superadmin']);
    }

    public function down(): void
    {
        Schema::table('escalation_levels', function (Blueprint $table) {
            $table->foreignId('custom_user_id')->nullable()->after('recipient_type');
            $table->string('email_override', 255)->nullable()->after('custom_email');
        });
    }
};
