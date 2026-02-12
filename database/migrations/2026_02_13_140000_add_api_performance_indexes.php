<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safe, production-friendly indexes for API performance.
 * - Minimal: only composite indexes that benefit filter/sort/aggregate queries.
 * - No redundant indexes (FKs and single-column indexes already exist where needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'expenses_user_id_status_index');
        });

        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->index(['user_id', 'login_at'], 'user_login_logs_user_id_login_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_user_id_status_index');
        });

        Schema::table('user_login_logs', function (Blueprint $table) {
            $table->dropIndex('user_login_logs_user_id_login_at_index');
        });
    }
};
