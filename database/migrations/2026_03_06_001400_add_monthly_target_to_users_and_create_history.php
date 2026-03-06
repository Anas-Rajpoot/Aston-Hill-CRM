<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('monthly_target', 12, 2)->nullable()->after('extension')
                  ->comment('Monthly activation target MRC (AED)');
        });

        Schema::create('user_monthly_target_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('month', 7)->comment('e.g. 2026-03');
            $table->decimal('target_amount', 12, 2);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'month']);
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_monthly_target_history');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_target');
        });
    }
};
