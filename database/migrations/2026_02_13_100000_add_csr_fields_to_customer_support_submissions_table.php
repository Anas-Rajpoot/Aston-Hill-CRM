<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->string('ticket_number', 100)->nullable()->after('submitted_at');
            $table->string('csr_name', 255)->nullable()->after('ticket_number');
            $table->string('workflow_status', 50)->nullable()->after('csr_name');
            $table->date('completion_date')->nullable()->after('workflow_status');
            $table->string('trouble_ticket', 255)->nullable()->after('completion_date');
            $table->string('activity', 255)->nullable()->after('trouble_ticket');
            $table->string('pending', 255)->nullable()->after('activity');
            $table->text('resolution_remarks')->nullable()->after('pending');
            $table->text('internal_remarks')->nullable()->after('resolution_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_number', 'csr_name', 'workflow_status', 'completion_date',
                'trouble_ticket', 'activity', 'pending', 'resolution_remarks', 'internal_remarks',
            ]);
        });
    }
};
