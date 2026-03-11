<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_triggers')) {
            return;
        }

        if (! Schema::hasColumn('notification_triggers', 'module')) {
            Schema::table('notification_triggers', function (Blueprint $table) {
                $table->string('module', 100)->nullable()->after('name');
            });
        }

        // Seed module values for existing triggers
        $modules = [
            'new_submission_created'    => 'All Modules',
            'submission_assigned'       => 'All Modules',
            'new_resubmission_created'  => 'All Modules',
            'status_updated'            => 'All Modules',
            'sla_approaching_breach'    => 'All Modules',
            'sla_breached'              => 'All Modules',
            'sla_followed'              => 'All Modules',
            'submission_rejected'       => 'Lead Submissions',
            'submission_completed'      => 'All Modules',
        ];

        if (Schema::hasColumn('notification_triggers', 'key')) {
            foreach ($modules as $key => $module) {
                DB::table('notification_triggers')
                    ->where('key', $key)
                    ->update(['module' => $module]);
            }
            return;
        }

        if (Schema::hasColumn('notification_triggers', 'name')) {
            foreach ($modules as $key => $module) {
                $displayName = str_replace('_', ' ', $key);
                $displayName = ucwords($displayName);

                DB::table('notification_triggers')
                    ->whereIn('name', [$key, $displayName])
                    ->update(['module' => $module]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_triggers')) {
            return;
        }

        if (Schema::hasColumn('notification_triggers', 'module')) {
            Schema::table('notification_triggers', function (Blueprint $table) {
                $table->dropColumn('module');
            });
        }
    }
};
