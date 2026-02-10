<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 50)->nullable()->unique()->after('id');
            $table->string('department', 100)->nullable()->after('team_leader_id');
            $table->string('extension', 20)->nullable()->after('department');
            $table->date('joining_date')->nullable()->after('extension');
            $table->date('terminate_date')->nullable()->after('joining_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['employee_number', 'department', 'extension', 'joining_date', 'terminate_date']);
        });
    }
};
