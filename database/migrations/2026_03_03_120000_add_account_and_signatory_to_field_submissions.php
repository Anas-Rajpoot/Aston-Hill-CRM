<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('field_submissions', 'account_number')) {
                $table->string('account_number')->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('field_submissions', 'authorized_signatory_name')) {
                $table->string('authorized_signatory_name')->nullable()->after('company_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('field_submissions', 'authorized_signatory_name')) {
                $table->dropColumn('authorized_signatory_name');
            }

            if (Schema::hasColumn('field_submissions', 'account_number')) {
                $table->dropColumn('account_number');
            }
        });
    }
};
