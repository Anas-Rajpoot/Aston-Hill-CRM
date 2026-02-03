<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow multiple files per document type (e.g. multiple trade_license files).
     * The unique index is used by the lead_submission_id foreign key, so we add
     * a plain index first, then drop the unique.
     */
    public function up(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->index('lead_submission_id');
        });
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->dropUnique(['lead_submission_id', 'doc_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->unique(['lead_submission_id', 'doc_key']);
        });
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->dropIndex(['lead_submission_id']);
        });
    }
};
