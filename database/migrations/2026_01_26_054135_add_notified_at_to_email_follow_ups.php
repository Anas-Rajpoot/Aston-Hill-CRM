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
        Schema::table('email_follow_ups', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('email_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_follow_ups', function (Blueprint $table) {
            //
        });
    }
};
