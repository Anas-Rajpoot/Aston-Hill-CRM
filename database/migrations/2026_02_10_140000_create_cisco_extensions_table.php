<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cisco_extensions', function (Blueprint $table) {
            $table->id();
            $table->string('extension', 50)->index();
            $table->string('landline_number', 50)->nullable()->index();
            $table->string('gateway', 100)->nullable()->index();
            $table->string('username', 100)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('status', 20)->default('active')->index(); // active, inactive, not_created
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cisco_extensions');
    }
};
