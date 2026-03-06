<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_monthly_target_history', function (Blueprint $table) {
            $table->dropUnique('user_monthly_target_history_user_id_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_monthly_target_history', function (Blueprint $table) {
            $table->unique(['user_id', 'month']);
        });
    }
};
