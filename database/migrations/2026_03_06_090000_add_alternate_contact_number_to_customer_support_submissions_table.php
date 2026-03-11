<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('customer_support_submissions', 'alternate_contact_number')) {
            Schema::table('customer_support_submissions', function (Blueprint $table) {
                $table->string('alternate_contact_number')->nullable()->after('contact_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customer_support_submissions', 'alternate_contact_number')) {
            Schema::table('customer_support_submissions', function (Blueprint $table) {
                $table->dropColumn('alternate_contact_number');
            });
        }
    }
};
