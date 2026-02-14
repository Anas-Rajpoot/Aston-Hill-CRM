<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton row for global email / channel settings
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_sender_email')->default('order@astonhill.ae');
            $table->json('cc_emails')->nullable();
            $table->json('bcc_emails')->nullable();
            $table->boolean('enable_email')->default(true);
            $table->boolean('enable_web')->default(true);
            $table->boolean('enable_sms')->default(false);
            $table->boolean('enable_sla_alerts')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Per-event toggle matrix
        Schema::create('notification_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->boolean('website_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_alert_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // SLA escalation levels
        Schema::create('notification_escalations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('level')->unique();
            $table->json('to_emails');
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Email templates (one per trigger)
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_key', 80)->index();
            $table->string('name', 200);
            $table->string('subject', 255);
            $table->text('body');
            $table->json('available_variables')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Notification delivery log
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_key', 80)->index();
            $table->string('channel', 20)->index();
            $table->string('module', 100)->nullable();
            $table->string('sent_to', 255);
            $table->string('status', 20)->default('sent')->index();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('notification_escalations');
        Schema::dropIfExists('notification_triggers');
        Schema::dropIfExists('notification_settings');
    }
};
