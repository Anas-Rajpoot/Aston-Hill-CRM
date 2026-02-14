<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_rules', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 80)->unique();        // slug: lead_submissions
            $table->string('module_name', 120);                 // display: Lead Submissions
            $table->unsignedInteger('sla_duration_minutes');     // total time before breach
            $table->unsignedInteger('warning_threshold_minutes');// warning time before breach
            $table->string('notification_email', 255);           // breach alert recipient
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('updated_at');
        });

        // Add entity tracking columns to system_audit_logs (generic reuse)
        if (Schema::hasTable('system_audit_logs')) {
            Schema::table('system_audit_logs', function (Blueprint $table) {
                $table->string('entity_type', 80)->nullable()->after('event');
                $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
                $table->index(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_rules');

        if (Schema::hasTable('system_audit_logs') && Schema::hasColumn('system_audit_logs', 'entity_type')) {
            Schema::table('system_audit_logs', function (Blueprint $table) {
                $table->dropIndex(['entity_type', 'entity_id']);
                $table->dropColumn(['entity_type', 'entity_id']);
            });
        }
    }
};
