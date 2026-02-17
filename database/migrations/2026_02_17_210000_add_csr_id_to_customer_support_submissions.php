<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a proper foreign key csr_id column to customer_support_submissions.
 *
 * This enables the same assignment workflow as leads (executive_id),
 * field (field_executive_id), and VAS (back_office_executive_id).
 *
 * The CSR can be assigned to handle specific customer support requests.
 * The existing csr_name string column is preserved for legacy data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->foreignId('csr_id')
                ->nullable()
                ->after('csr_name')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('csr_id', 'idx_cs_csr_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropForeign(['csr_id']);
            $table->dropIndex('idx_cs_csr_id');
            $table->dropColumn('csr_id');
        });
    }
};
