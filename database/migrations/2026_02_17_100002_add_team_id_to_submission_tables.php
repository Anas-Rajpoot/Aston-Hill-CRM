<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'lead_submissions',
        'field_submissions',
        'customer_support_submissions',
        'vas_request_submissions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'team_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('team_id')->nullable()->after('manager_id');
                    $t->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'team_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropForeign(["{$table}_team_id_foreign"]);
                    $t->dropColumn('team_id');
                });
            }
        }
    }
};
