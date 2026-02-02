<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_role_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('slot_key', 50)->unique(); // manager, team_leader, sales_agent
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_role_mappings');
    }
};
