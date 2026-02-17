<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('team_leader_id')->nullable();
            $table->string('department')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('max_members')->nullable();
            $table->timestamps();

            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('team_leader_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
