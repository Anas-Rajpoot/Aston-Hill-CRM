<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->foreignId('field_executive_id')->nullable()->after('sales_agent_id')->constrained('users')->nullOnDelete();
            $table->date('meeting_date')->nullable()->after('field_executive_id');
            $table->string('field_status', 80)->nullable()->after('meeting_date'); // e.g. Meeting Scheduled, Visited
            $table->text('remarks_by_field_agent')->nullable()->after('field_status');
        });
    }

    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropForeign(['field_executive_id']);
            $table->dropColumn(['meeting_date', 'field_status', 'remarks_by_field_agent']);
        });
    }
};
