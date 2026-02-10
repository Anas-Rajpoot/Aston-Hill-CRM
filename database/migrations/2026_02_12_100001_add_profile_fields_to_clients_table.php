<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Overview card: revenue, CSR names.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('revenue', 14, 2)->nullable()->after('additional_notes');
            $table->string('csr_name_1', 100)->nullable()->after('revenue');
            $table->string('csr_name_2', 100)->nullable()->after('csr_name_1');
            $table->string('csr_name_3', 100)->nullable()->after('csr_name_2');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['revenue', 'csr_name_1', 'csr_name_2', 'csr_name_3']);
        });
    }
};
