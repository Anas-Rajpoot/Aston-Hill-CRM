<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_inheritance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('child_role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['parent_role_id', 'child_role_id'], 'role_inheritance_unique_edge');
            $table->index(['child_role_id', 'parent_role_id'], 'role_inheritance_child_parent_idx');
        });

        // Prevent self-referential inheritance edges at DB level.
        DB::statement('ALTER TABLE role_inheritance ADD CONSTRAINT role_inheritance_no_self_edge CHECK (parent_role_id <> child_role_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('role_inheritance');
    }
};
