<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'status')) {
                $table->index(['status', 'submitted_at'], 'idx_clients_status_submitted_at');
            }
            if (Schema::hasColumn('clients', 'manager_id')) {
                $table->index('manager_id', 'idx_clients_manager_id');
            }
            if (Schema::hasColumn('clients', 'team_leader_id')) {
                $table->index('team_leader_id', 'idx_clients_team_leader_id');
            }
            if (Schema::hasColumn('clients', 'sales_agent_id')) {
                $table->index('sales_agent_id', 'idx_clients_sales_agent_id');
            }
            if (Schema::hasColumn('clients', 'created_at')) {
                $table->index('created_at', 'idx_clients_created_at');
            }
            if (Schema::hasColumn('clients', 'company_name')) {
                $table->index('company_name', 'idx_clients_company_name');
            }
            if (Schema::hasColumn('clients', 'account_number')) {
                $table->index('account_number', 'idx_clients_account_number');
            }
        });

        Schema::table('client_company_details', function (Blueprint $table) {
            if (Schema::hasColumn('client_company_details', 'client_id')) {
                $table->index('client_id', 'idx_client_company_details_client_id');
            }
            if (Schema::hasColumn('client_company_details', 'trade_license_expiry_date')) {
                $table->index('trade_license_expiry_date', 'idx_client_company_details_trade_license_expiry_date');
            }
            if (Schema::hasColumn('client_company_details', 'establishment_card_expiry_date')) {
                $table->index('establishment_card_expiry_date', 'idx_client_company_details_establishment_card_expiry_date');
            }
            if (Schema::hasColumn('client_company_details', 'company_category')) {
                $table->index('company_category', 'idx_client_company_details_company_category');
            }
        });

        Schema::table('client_alerts', function (Blueprint $table) {
            if (Schema::hasColumn('client_alerts', 'client_id')) {
                $table->index('client_id', 'idx_client_alerts_client_id');
            }
            if (Schema::hasColumn('client_alerts', 'alert_type')) {
                $table->index('alert_type', 'idx_client_alerts_alert_type');
            }
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('clients', 'idx_clients_status_submitted_at');
        $this->dropIndexIfExists('clients', 'idx_clients_manager_id');
        $this->dropIndexIfExists('clients', 'idx_clients_team_leader_id');
        $this->dropIndexIfExists('clients', 'idx_clients_sales_agent_id');
        $this->dropIndexIfExists('clients', 'idx_clients_created_at');
        $this->dropIndexIfExists('clients', 'idx_clients_company_name');
        $this->dropIndexIfExists('clients', 'idx_clients_account_number');

        $this->dropIndexIfExists('client_company_details', 'idx_client_company_details_client_id');
        $this->dropIndexIfExists('client_company_details', 'idx_client_company_details_trade_license_expiry_date');
        $this->dropIndexIfExists('client_company_details', 'idx_client_company_details_establishment_card_expiry_date');
        $this->dropIndexIfExists('client_company_details', 'idx_client_company_details_company_category');

        $this->dropIndexIfExists('client_alerts', 'idx_client_alerts_client_id');
        $this->dropIndexIfExists('client_alerts', 'idx_client_alerts_alert_type');
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }
};

