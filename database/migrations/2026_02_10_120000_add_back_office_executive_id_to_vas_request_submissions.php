<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('vas_request_submissions', 'back_office_executive_id')) {
                $table->foreignId('back_office_executive_id')->nullable()->after('sales_agent_id')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('vas_request_submissions', 'back_office_executive_id')) {
            Schema::table('vas_request_submissions', function (Blueprint $table) {
                $table->dropForeign(['back_office_executive_id']);
            });
        }
    }
};
