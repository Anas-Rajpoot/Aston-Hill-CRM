<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['status', 'submitted_at', 'id'], 'idx_clients_status_submitted_id');
            $table->index(['manager_id', 'submitted_at', 'id'], 'idx_clients_manager_submitted_id');
            $table->index(['team_leader_id', 'submitted_at', 'id'], 'idx_clients_team_leader_submitted_id');
            $table->index(['sales_agent_id', 'submitted_at', 'id'], 'idx_clients_sales_agent_submitted_id');
            $table->index(['account_number', 'status'], 'idx_clients_account_status');
            $table->index(['service_category', 'service_type', 'product_type'], 'idx_clients_service_triplet');
        });

        Schema::table('client_company_details', function (Blueprint $table) {
            $table->index(['company_category', 'account_manager_name'], 'idx_client_company_cat_manager');
        });

        Schema::table('client_alerts', function (Blueprint $table) {
            $table->index(['alert_type', 'client_id'], 'idx_client_alerts_type_client');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_status_submitted_id');
            $table->dropIndex('idx_clients_manager_submitted_id');
            $table->dropIndex('idx_clients_team_leader_submitted_id');
            $table->dropIndex('idx_clients_sales_agent_submitted_id');
            $table->dropIndex('idx_clients_account_status');
            $table->dropIndex('idx_clients_service_triplet');
        });

        Schema::table('client_company_details', function (Blueprint $table) {
            $table->dropIndex('idx_client_company_cat_manager');
        });

        Schema::table('client_alerts', function (Blueprint $table) {
            $table->dropIndex('idx_client_alerts_type_client');
        });
    }
};

