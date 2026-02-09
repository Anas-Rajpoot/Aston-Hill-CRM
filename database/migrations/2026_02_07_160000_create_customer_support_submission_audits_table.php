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
        Schema::create('customer_support_submission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_support_submission_id')->constrained('customer_support_submissions')->cascadeOnDelete();
            $table->string('field_name', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('customer_support_submission_audits', function (Blueprint $table) {
            $table->index(['customer_support_submission_id', 'changed_at']);
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_support_submission_audits');
    }
};
