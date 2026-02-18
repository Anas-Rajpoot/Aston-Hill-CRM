<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('vas_request_submissions', 'request_description')) {
                $table->renameColumn('request_description', 'description');
            } elseif (!Schema::hasColumn('vas_request_submissions', 'description')) {
                $table->text('description')->nullable()->after('request_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vas_request_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('vas_request_submissions', 'description')) {
                $table->renameColumn('description', 'request_description');
            }
        });
    }
};
