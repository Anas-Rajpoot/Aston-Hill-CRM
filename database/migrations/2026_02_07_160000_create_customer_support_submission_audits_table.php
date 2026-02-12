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
            $table->unsignedBigInteger('customer_support_submission_id');
            $table->string('field_name', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_support_submission_id', 'css_audits_submission_id_fk')
                ->references('id')->on('customer_support_submissions')->cascadeOnDelete();
            $table->foreign('changed_by', 'css_audits_changed_by_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('customer_support_submission_audits', function (Blueprint $table) {
            $table->index(['customer_support_submission_id', 'changed_at'], 'css_audits_submission_changed_at_idx');
            $table->index('changed_at', 'css_audits_changed_at_idx');
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
