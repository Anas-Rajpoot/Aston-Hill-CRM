<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'team_id')) {
                $table->unsignedBigInteger('team_id')->nullable()->after('team_leader_id');
                $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'reports_to')) {
                $table->unsignedBigInteger('reports_to')->nullable()->after('team_id');
                $table->foreign('reports_to')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reports_to')) {
                $table->dropForeign(['reports_to']);
                $table->dropColumn('reports_to');
            }
            if (Schema::hasColumn('users', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            }
        });
    }
};
