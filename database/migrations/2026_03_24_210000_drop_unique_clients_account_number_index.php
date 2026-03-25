<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS uq_clients_account_number');
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('uq_clients_account_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_clients_account_number ON clients(account_number)');
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unique('account_number', 'uq_clients_account_number');
        });
    }
};

