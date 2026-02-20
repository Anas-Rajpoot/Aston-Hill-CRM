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
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->index('field_executive_id', 'fs_field_executive_idx');
            $table->index('field_status', 'fs_field_status_idx');
            $table->index('submitted_at', 'fs_submitted_at_idx');
            $table->index(['field_status', 'field_executive_id'], 'fs_status_executive_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropIndex('fs_field_executive_idx');
            $table->dropIndex('fs_field_status_idx');
            $table->dropIndex('fs_submitted_at_idx');
            $table->dropIndex('fs_status_executive_idx');
        });
    }
};
