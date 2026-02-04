<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for lead submissions listing: sort, filter, and visibility scope.
     * Keeps list API under 1s by avoiding full table scans.
     */
    public function up(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->index('submitted_at');
            $table->index('updated_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
