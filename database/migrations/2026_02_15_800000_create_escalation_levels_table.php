<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escalation levels for SLA breach notifications.
 *
 * Each level defines a recipient type, optional override email,
 * and the order in which escalations fire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('level')->unique();
            $table->enum('recipient_type', [
                'assignee',      // submission's assigned user
                'manager',       // manager_id of the assignee
                'admin',         // super_admin role users
                'custom_user',   // specific user chosen by admin
                'custom_email',  // arbitrary email address
            ])->default('assignee');
            $table->foreignId('custom_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('custom_email', 255)->nullable();
            $table->string('email_override', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_levels');
    }
};
