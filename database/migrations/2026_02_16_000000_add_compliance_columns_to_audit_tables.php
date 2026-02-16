<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliance fix: Add ip_address and user_agent columns to all module-specific
 * audit tables that are currently missing them.
 *
 * Affected tables (8):
 *   - user_audits
 *   - lead_submission_audits
 *   - field_submission_audits
 *   - customer_support_submission_audits
 *   - vas_request_audits
 *   - client_audits
 *   - expense_audits
 *   - cisco_extension_audits
 *
 * The global `audit_logs` table already has these columns (as `ip` and `user_agent`).
 *
 * Run:  php artisan migrate
 */
return new class extends Migration
{
    private array $tables = [
        'user_audits',
        'lead_submission_audits',
        'field_submission_audits',
        'customer_support_submission_audits',
        'vas_request_audits',
        'client_audits',
        'expense_audits',
        'cisco_extension_audits',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'ip_address')) {
                    $t->string('ip_address', 45)->nullable()->after(
                        Schema::hasColumn($table, 'changed_by') ? 'changed_by'
                        : (Schema::hasColumn($table, 'user_id') ? 'user_id' : 'id')
                    );
                }

                if (! Schema::hasColumn($table, 'user_agent')) {
                    $t->text('user_agent')->nullable()->after('ip_address');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $dropCols = [];
                if (Schema::hasColumn($table, 'ip_address')) {
                    $dropCols[] = 'ip_address';
                }
                if (Schema::hasColumn($table, 'user_agent')) {
                    $dropCols[] = 'user_agent';
                }
                if ($dropCols) {
                    $t->dropColumn($dropCols);
                }
            });
        }
    }
};
