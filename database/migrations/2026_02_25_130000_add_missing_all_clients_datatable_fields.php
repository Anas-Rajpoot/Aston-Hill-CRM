<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'submission_type')) {
                $table->string('submission_type', 100)->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('clients', 'service_category')) {
                $table->string('service_category', 100)->nullable()->after('submission_type');
            }

            if (! Schema::hasColumn('clients', 'sales_agent_id')) {
                $table->foreignId('sales_agent_id')->nullable()->constrained('users')->nullOnDelete()->after('team_leader_id');
            }

            if (! Schema::hasColumn('clients', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('wo_number');
            }

            if (! Schema::hasColumn('clients', 'clawback_chum')) {
                $table->string('clawback_chum', 10)->nullable()->after('contract_end_date');
            }

            if (! Schema::hasColumn('clients', 'renewal_alert')) {
                $table->unsignedInteger('renewal_alert')->nullable()->after('clawback_chum');
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive in mixed environments.
    }
};

