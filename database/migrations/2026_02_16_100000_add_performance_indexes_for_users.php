<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for users and related tables.
 * Skips adding an index if it already exists (safe to re-run after partial run).
 */
return new class extends Migration
{
    private function indexExists(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            foreach ($indexes as $index) {
                if (($index->name ?? null) === $name) {
                    return true;
                }
            }

            return false;
        }

        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

        return count($indexes) > 0;
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! $this->indexExists('users', 'users_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'users_status_created_at_index');
            }
            if (! $this->indexExists('users', 'users_manager_id_index')) {
                $table->index('manager_id', 'users_manager_id_index');
            }
            if (! $this->indexExists('users', 'users_team_leader_id_index')) {
                $table->index('team_leader_id', 'users_team_leader_id_index');
            }
        });

        if (Schema::hasTable('user_login_logs') && ! $this->indexExists('user_login_logs', 'user_login_logs_user_id_login_at_index')) {
            Schema::table('user_login_logs', function (Blueprint $table) {
                $table->index(['user_id', 'login_at'], 'user_login_logs_user_id_login_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_status_created_at_index')) {
                $table->dropIndex('users_status_created_at_index');
            }
            if ($this->indexExists('users', 'users_manager_id_index')) {
                $table->dropIndex('users_manager_id_index');
            }
            if ($this->indexExists('users', 'users_team_leader_id_index')) {
                $table->dropIndex('users_team_leader_id_index');
            }
        });
        if (Schema::hasTable('user_login_logs') && $this->indexExists('user_login_logs', 'user_login_logs_user_id_login_at_index')) {
            Schema::table('user_login_logs', function (Blueprint $table) {
                $table->dropIndex('user_login_logs_user_id_login_at_index');
            });
        }
    }
};
