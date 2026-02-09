<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks who changed which field, old value, new value, and when.
     */
    public function up(): void
    {
        Schema::create('field_submission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_submission_id')->constrained('field_submissions')->cascadeOnDelete();
            $table->string('field_name', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('field_submission_audits', function (Blueprint $table) {
            $table->index(['field_submission_id', 'changed_at']);
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_submission_audits');
    }
};
