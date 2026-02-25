<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'submission_type')) {
                $table->string('submission_type', 100)->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('clients', 'service_category')) {
                $table->string('service_category', 100)->nullable()->after('submission_type');
            }
            if (!Schema::hasColumn('clients', 'work_order_status')) {
                $table->string('work_order_status', 100)->nullable()->after('wo_number');
            }
            if (!Schema::hasColumn('clients', 'activation_date')) {
                $table->date('activation_date')->nullable()->after('work_order_status');
            }
            if (!Schema::hasColumn('clients', 'clawback_chum')) {
                $table->string('clawback_chum', 10)->nullable()->after('contract_end_date');
            }
            if (!Schema::hasColumn('clients', 'remarks')) {
                $table->string('remarks', 500)->nullable()->after('clawback_chum');
            }
        });

        // Backfill values stored in legacy fields to improve old records display.
        if (Schema::hasColumn('clients', 'activity') && Schema::hasColumn('clients', 'other')) {
            DB::statement("UPDATE clients SET activity = other WHERE activity IS NULL AND other IS NOT NULL AND other <> ''");
        }
        if (Schema::hasColumn('clients', 'remarks') && Schema::hasColumn('clients', 'additional_notes')) {
            DB::statement("UPDATE clients SET remarks = additional_notes WHERE remarks IS NULL AND additional_notes IS NOT NULL AND additional_notes <> ''");
        }
        if (Schema::hasColumn('clients', 'activation_date') && Schema::hasColumn('clients', 'submitted_at')) {
            DB::statement("UPDATE clients SET activation_date = DATE(submitted_at) WHERE activation_date IS NULL AND submitted_at IS NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $drop = [];
            foreach (['submission_type', 'service_category', 'work_order_status', 'activation_date', 'clawback_chum', 'remarks'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};

