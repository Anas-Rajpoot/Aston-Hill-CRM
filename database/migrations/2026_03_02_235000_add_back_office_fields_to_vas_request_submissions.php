<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('vas_request_submissions', 'activity')) {
                $table->string('activity', 255)->nullable()->after('status');
            }
            if (! Schema::hasColumn('vas_request_submissions', 'completion_date')) {
                $table->date('completion_date')->nullable()->after('activity');
            }
            if (! Schema::hasColumn('vas_request_submissions', 'remarks')) {
                $table->text('remarks')->nullable()->after('completion_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('vas_request_submissions', 'remarks')) {
                $table->dropColumn('remarks');
            }
            if (Schema::hasColumn('vas_request_submissions', 'completion_date')) {
                $table->dropColumn('completion_date');
            }
            if (Schema::hasColumn('vas_request_submissions', 'activity')) {
                $table->dropColumn('activity');
            }
        });
    }
};
