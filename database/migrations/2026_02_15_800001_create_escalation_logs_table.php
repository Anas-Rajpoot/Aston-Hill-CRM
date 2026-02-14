<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks which escalation levels have been sent for each submission,
 * preventing duplicate escalation notifications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 80)->index();      // e.g. 'lead_submissions'
            $table->unsignedBigInteger('record_id')->index(); // submission ID
            $table->unsignedSmallInteger('escalation_level');
            $table->string('sent_to', 255);
            $table->string('recipient_type', 30);
            $table->enum('status', ['sent', 'failed', 'skipped'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['module_key', 'record_id', 'escalation_level'], 'esc_log_unique');
            $table->index(['module_key', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_logs');
    }
};
