<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_submission_documents', 'label')) {
                $table->string('label')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            if (Schema::hasColumn('lead_submission_documents', 'label')) {
                $table->dropColumn('label');
            }
        });
    }
};
