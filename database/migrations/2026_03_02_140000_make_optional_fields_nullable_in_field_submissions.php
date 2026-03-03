<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->string('alternate_number')->nullable()->change();
            $table->unsignedBigInteger('team_leader_id')->nullable()->change();
            $table->unsignedBigInteger('sales_agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->string('alternate_number')->nullable(false)->change();
            $table->unsignedBigInteger('team_leader_id')->nullable(false)->change();
            $table->unsignedBigInteger('sales_agent_id')->nullable(false)->change();
        });
    }
};
