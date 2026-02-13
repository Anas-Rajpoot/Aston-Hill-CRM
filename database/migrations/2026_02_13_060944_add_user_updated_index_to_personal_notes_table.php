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
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->index(['user_id', 'updated_at', 'created_at'], 'personal_notes_user_updated_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_notes', function (Blueprint $table) {
            $table->dropIndex('personal_notes_user_updated_index');
        });
    }
};
