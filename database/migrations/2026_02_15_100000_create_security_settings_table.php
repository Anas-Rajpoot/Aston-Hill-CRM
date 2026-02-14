<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->id();

            // Session Management
            $table->unsignedInteger('auto_logout_after_minutes')->default(30);
            $table->unsignedInteger('session_warning_minutes')->default(5);
            $table->boolean('force_logout_on_close')->default(false);
            $table->boolean('prevent_multiple_sessions')->default(false);

            // Login & Account Security
            $table->unsignedInteger('max_login_attempts')->default(5);
            $table->boolean('lock_after_failed_attempts')->default(true);
            $table->unsignedInteger('lock_duration_minutes')->default(30);
            $table->boolean('force_password_reset_on_first_login')->default(true);

            // Password Policies
            $table->unsignedInteger('min_length')->default(8);
            $table->boolean('require_uppercase')->default(true);
            $table->boolean('require_number')->default(true);
            $table->boolean('require_special')->default(true);
            $table->unsignedInteger('password_expiry_days')->default(90);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
    }
};
