<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes on assigned_to + created_at for fast RBAC scoping queries.
 * Also adds executive_id index for lead_submissions (used by back office assignment flow).
 *
 * These composites let MySQL satisfy WHERE assigned_to = ? ORDER BY created_at DESC
 * entirely from the index without a filesort.
 */
return new class extends Migration
{
    public function up(): void
    {
        // lead_submissions: executive_id is the assignment column
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->index(['executive_id', 'created_at'], 'idx_leads_exec_created');
        });

        // field_submissions: field_executive_id is the assignment column
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->index(['field_executive_id', 'created_at'], 'idx_field_exec_created');
        });

        // customer_support_submissions: csr_id is the assignment column
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->index(['csr_id', 'created_at'], 'idx_cs_csr_created');
        });

        // vas_request_submissions: back_office_executive_id is the assignment column
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->index(['back_office_executive_id', 'created_at'], 'idx_vas_boexec_created');
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_leads_exec_created');
        });
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_field_exec_created');
        });
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_cs_csr_created');
        });
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_vas_boexec_created');
        });
    }
};
