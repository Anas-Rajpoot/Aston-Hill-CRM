<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the four submission tables.
 *
 * These composite indexes are designed to cover the most common
 * WHERE + ORDER BY patterns used by listing and report endpoints.
 *
 * Index naming convention: idx_{table}_{purpose}
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────
        // lead_submissions
        // ──────────────────────────────────────────
        Schema::table('lead_submissions', function (Blueprint $table) {
            // Listing: WHERE status != 'draft' ORDER BY submitted_at DESC
            $table->index(['status', 'submitted_at'], 'idx_leads_status_submitted');

            // RBAC scoping: WHERE created_by = ? OR sales_agent_id = ? OR executive_id = ?
            $table->index('created_by', 'idx_leads_created_by');
            $table->index('sales_agent_id', 'idx_leads_sales_agent');
            $table->index('team_leader_id', 'idx_leads_team_leader');
            $table->index('manager_id', 'idx_leads_manager');
            $table->index('team_id', 'idx_leads_team');

            // Reports: date range + status filtering
            $table->index(['submitted_at', 'status'], 'idx_leads_submitted_status');

            // Text search on company name (prefix search)
            $table->index('company_name', 'idx_leads_company_name');
            $table->index('account_number', 'idx_leads_account_number');
        });

        // ──────────────────────────────────────────
        // field_submissions
        // ──────────────────────────────────────────
        Schema::table('field_submissions', function (Blueprint $table) {
            // Listing: WHERE status != 'draft' ORDER BY submitted_at DESC
            $table->index(['status', 'submitted_at'], 'idx_field_status_submitted');

            // RBAC scoping
            $table->index('created_by', 'idx_field_created_by');
            $table->index('sales_agent_id', 'idx_field_sales_agent');
            $table->index('team_leader_id', 'idx_field_team_leader');
            $table->index('manager_id', 'idx_field_manager');
            $table->index('team_id', 'idx_field_team');

            // Reports: date range filtering
            $table->index('submitted_at', 'idx_field_submitted_at');

            // Text search
            $table->index('company_name', 'idx_field_company_name');
        });

        // ──────────────────────────────────────────
        // customer_support_submissions
        // ──────────────────────────────────────────
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            // Listing: WHERE status != 'draft' ORDER BY submitted_at DESC
            $table->index(['status', 'submitted_at'], 'idx_cs_status_submitted');

            // RBAC scoping
            $table->index('created_by', 'idx_cs_created_by');
            $table->index('sales_agent_id', 'idx_cs_sales_agent');
            $table->index('team_leader_id', 'idx_cs_team_leader');
            $table->index('manager_id', 'idx_cs_manager');
            $table->index('team_id', 'idx_cs_team');

            // Reports: date range filtering
            $table->index('submitted_at', 'idx_cs_submitted_at');

            // Text search / filter
            $table->index('company_name', 'idx_cs_company_name');
            $table->index('account_number', 'idx_cs_account_number');
            $table->index('issue_category', 'idx_cs_issue_category');
        });

        // ──────────────────────────────────────────
        // vas_request_submissions  (had ZERO indexes)
        // ──────────────────────────────────────────
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            // Primary listing: WHERE status != 'draft' ORDER BY submitted_at DESC
            $table->index(['status', 'created_at'], 'idx_vas_status_created');
            $table->index(['status', 'submitted_at'], 'idx_vas_status_submitted');

            // RBAC scoping
            $table->index('created_by', 'idx_vas_created_by');
            $table->index('sales_agent_id', 'idx_vas_sales_agent');
            $table->index('team_leader_id', 'idx_vas_team_leader');
            $table->index('manager_id', 'idx_vas_manager');
            $table->index('back_office_executive_id', 'idx_vas_bo_executive');
            $table->index('team_id', 'idx_vas_team');

            // Reports: date range + request type
            $table->index('submitted_at', 'idx_vas_submitted_at');
            $table->index('request_type', 'idx_vas_request_type');

            // Text search
            $table->index('company_name', 'idx_vas_company_name');
            $table->index('account_number', 'idx_vas_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_leads_status_submitted');
            $table->dropIndex('idx_leads_created_by');
            $table->dropIndex('idx_leads_sales_agent');
            $table->dropIndex('idx_leads_team_leader');
            $table->dropIndex('idx_leads_manager');
            $table->dropIndex('idx_leads_team');
            $table->dropIndex('idx_leads_submitted_status');
            $table->dropIndex('idx_leads_company_name');
            $table->dropIndex('idx_leads_account_number');
        });

        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_field_status_submitted');
            $table->dropIndex('idx_field_created_by');
            $table->dropIndex('idx_field_sales_agent');
            $table->dropIndex('idx_field_team_leader');
            $table->dropIndex('idx_field_manager');
            $table->dropIndex('idx_field_team');
            $table->dropIndex('idx_field_submitted_at');
            $table->dropIndex('idx_field_company_name');
        });

        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_cs_status_submitted');
            $table->dropIndex('idx_cs_created_by');
            $table->dropIndex('idx_cs_sales_agent');
            $table->dropIndex('idx_cs_team_leader');
            $table->dropIndex('idx_cs_manager');
            $table->dropIndex('idx_cs_team');
            $table->dropIndex('idx_cs_submitted_at');
            $table->dropIndex('idx_cs_company_name');
            $table->dropIndex('idx_cs_account_number');
            $table->dropIndex('idx_cs_issue_category');
        });

        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->dropIndex('idx_vas_status_created');
            $table->dropIndex('idx_vas_status_submitted');
            $table->dropIndex('idx_vas_created_by');
            $table->dropIndex('idx_vas_sales_agent');
            $table->dropIndex('idx_vas_team_leader');
            $table->dropIndex('idx_vas_manager');
            $table->dropIndex('idx_vas_bo_executive');
            $table->dropIndex('idx_vas_team');
            $table->dropIndex('idx_vas_submitted_at');
            $table->dropIndex('idx_vas_request_type');
            $table->dropIndex('idx_vas_company_name');
            $table->dropIndex('idx_vas_account_number');
        });
    }
};
