<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores whether the lead is a new submission or a resubmission (rejected then resubmitted).
     */
    public function up(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->string('submission_type', 20)->nullable()->after('status')->comment('new or resubmission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropColumn('submission_type');
        });
    }
};
