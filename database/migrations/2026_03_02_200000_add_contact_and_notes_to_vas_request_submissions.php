<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('vas_request_submissions', 'contact_number')) {
                $table->string('contact_number', 50)->nullable()->after('account_number');
            }

            if (! Schema::hasColumn('vas_request_submissions', 'additional_notes')) {
                $table->text('additional_notes')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('vas_request_submissions', 'contact_number')) {
                $table->dropColumn('contact_number');
            }

            if (Schema::hasColumn('vas_request_submissions', 'additional_notes')) {
                $table->dropColumn('additional_notes');
            }
        });
    }
};
