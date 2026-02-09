<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores each field-level change: field name, old value, new value, when, who.
     */
    public function up(): void
    {
        Schema::create('lead_submission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_submission_id')->constrained('lead_submissions')->cascadeOnDelete();
            $table->string('field_name', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('lead_submission_audits', function (Blueprint $table) {
            $table->index(['lead_submission_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_submission_audits');
    }
};
