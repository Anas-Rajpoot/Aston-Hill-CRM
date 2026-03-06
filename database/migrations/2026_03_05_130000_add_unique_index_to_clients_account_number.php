<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'account_number')) {
            return;
        }

        $rows = DB::table('clients')
            ->select('id', 'account_number')
            ->whereNotNull('account_number')
            ->whereRaw('TRIM(account_number) <> ?', [''])
            ->orderBy('id')
            ->get();

        $seen = [];
        $idsToNull = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row->account_number));
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $idsToNull[] = (int) $row->id;
                continue;
            }
            $seen[$key] = true;
        }

        if (! empty($idsToNull)) {
            DB::table('clients')->whereIn('id', $idsToNull)->update(['account_number' => null]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->unique('account_number', 'uq_clients_account_number');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique('uq_clients_account_number');
        });
    }
};
