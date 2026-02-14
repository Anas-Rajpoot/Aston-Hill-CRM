<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('timezone', 100)->default('Asia/Dubai');
            $table->string('default_dashboard_landing_page', 50)->default('dashboard');
            $table->unsignedSmallInteger('default_table_page_size')->default(25);
            $table->boolean('auto_refresh_dashboard')->default(false);
            $table->unsignedSmallInteger('auto_refresh_interval_minutes')->default(5);
            $table->boolean('auto_save_draft_forms')->default(true);
            $table->boolean('session_warning_before_logout')->default(true);
            $table->unsignedSmallInteger('session_warning_minutes')->default(5);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Audit log for system-level settings changes
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 100)->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
        Schema::dropIfExists('system_preferences');
    }
};
