<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->datetime('occurred_at')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->index();
            $table->string('user_role')->nullable()->index();
            $table->string('action')->index();           // created, updated, deleted, login, logout, etc.
            $table->string('module')->index();            // leads, submissions, employees, auth, system, etc.
            $table->string('record_id')->nullable()->index();
            $table->string('record_ref')->nullable();     // human-readable ref
            $table->enum('result', ['success', 'failure'])->default('success')->index();
            $table->string('ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();         // parsed UA label
            $table->string('session_id')->nullable()->index();
            $table->string('route')->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            // Composite indexes for common filter combos
            $table->index(['module', 'action', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['result', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
