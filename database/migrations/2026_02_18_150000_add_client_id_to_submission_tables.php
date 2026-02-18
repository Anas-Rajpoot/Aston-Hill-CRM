<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'lead_submissions',
        'field_submissions',
        'vas_request_submissions',
        'customer_support_submissions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('client_id')->nullable()->after('id');
                    $t->index('client_id');
                    $t->foreign('client_id')
                      ->references('id')
                      ->on('clients')
                      ->onDelete('set null');
                });
            }
        }

        // Backfill: link existing records by account_number
        foreach (self::TABLES as $table) {
            $accountCol = Schema::hasColumn($table, 'account_number') ? 'account_number' : null;
            if (! $accountCol) {
                continue;
            }

            DB::statement("
                UPDATE `{$table}` AS s
                INNER JOIN `clients` AS c ON LOWER(TRIM(s.`account_number`)) = LOWER(TRIM(c.`account_number`))
                SET s.`client_id` = c.`id`
                WHERE s.`client_id` IS NULL
                  AND s.`account_number` IS NOT NULL
                  AND s.`account_number` != ''
            ");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'client_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['client_id']);
                    $t->dropColumn('client_id');
                });
            }
        }
    }
};
