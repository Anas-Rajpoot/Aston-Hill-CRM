<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for submission tables.
 *
 * Adds indexes on foreign key columns and commonly filtered/sorted columns
 * across lead_submissions, field_submissions, vas_request_submissions,
 * customer_support_submissions, and expenses tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- lead_submissions ---
        Schema::table('lead_submissions', function (Blueprint $table) {
            // FK columns used in visibleTo() scopes & joins
            if (! $this->hasIndex('lead_submissions', 'lead_submissions_sales_agent_id_index')) {
                $table->index('sales_agent_id');
            }
            if (! $this->hasIndex('lead_submissions', 'lead_submissions_team_leader_id_index')) {
                $table->index('team_leader_id');
            }
            if (! $this->hasIndex('lead_submissions', 'lead_submissions_manager_id_index')) {
                $table->index('manager_id');
            }
            if (! $this->hasIndex('lead_submissions', 'lead_submissions_submitted_at_index')) {
                $table->index('submitted_at');
            }
        });

        // --- field_submissions ---
        Schema::table('field_submissions', function (Blueprint $table) {
            if (! $this->hasIndex('field_submissions', 'field_submissions_sales_agent_id_index')) {
                $table->index('sales_agent_id');
            }
            if (! $this->hasIndex('field_submissions', 'field_submissions_submitted_at_index')) {
                $table->index('submitted_at');
            }
        });

        // --- vas_request_submissions ---
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (! $this->hasIndex('vas_request_submissions', 'vas_request_submissions_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
            if (! $this->hasIndex('vas_request_submissions', 'vas_request_submissions_submitted_at_index')) {
                $table->index('submitted_at');
            }
            if (! $this->hasIndex('vas_request_submissions', 'vas_request_submissions_sales_agent_id_index')) {
                $table->index('sales_agent_id');
            }
            if (! $this->hasIndex('vas_request_submissions', 'vas_request_submissions_manager_id_index')) {
                $table->index('manager_id');
            }
            if (! $this->hasIndex('vas_request_submissions', 'vas_request_submissions_created_by_index')) {
                $table->index('created_by');
            }
        });

        // --- customer_support_submissions ---
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            if (! $this->hasIndex('customer_support_submissions', 'customer_support_submissions_sales_agent_id_index')) {
                $table->index('sales_agent_id');
            }
            if (! $this->hasIndex('customer_support_submissions', 'customer_support_submissions_manager_id_index')) {
                $table->index('manager_id');
            }
        });

        // --- expenses (status column if it exists) ---
        if (Schema::hasColumn('expenses', 'status')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (! $this->hasIndex('expenses', 'expenses_status_index')) {
                    $table->index('status');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropIndex(['sales_agent_id']);
            $table->dropIndex(['team_leader_id']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['submitted_at']);
        });
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropIndex(['sales_agent_id']);
            $table->dropIndex(['submitted_at']);
        });
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['submitted_at']);
            $table->dropIndex(['sales_agent_id']);
            $table->dropIndex(['manager_id']);
            $table->dropIndex(['created_by']);
        });
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropIndex(['sales_agent_id']);
            $table->dropIndex(['manager_id']);
        });
        if (Schema::hasColumn('expenses', 'status')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }
    }

    /**
     * Check if an index already exists on a table.
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Fallback: assume no index
        }
        return false;
    }
};
