<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('session_id')->nullable()->index();
            $table->timestamp('login_at')->nullable()->index();
            $table->timestamp('logout_at')->nullable()->index();

            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();

            $table->string('country')->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('city', 100)->nullable();

            // snapshot role at login time (useful for reporting even if role changes later)
            $table->string('role')->nullable()->index();

            // suspicious flags
            $table->boolean('is_suspicious')->default(false)->index();
            $table->string('suspicious_reason')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
