<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->foreignId('executive_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete();
            $table->string('call_verification', 50)->nullable()->after('status_changed_at');
            $table->string('pending_from_sales', 50)->nullable()->after('call_verification');
            $table->string('documents_verification', 50)->nullable()->after('pending_from_sales');
            $table->date('submission_date_from')->nullable()->after('documents_verification');
            $table->text('back_office_notes')->nullable()->after('remarks');
            $table->string('activity', 255)->nullable()->after('back_office_notes');
            $table->string('back_office_account', 100)->nullable()->after('activity');
            $table->string('work_order', 255)->nullable()->after('back_office_account');
            $table->string('du_status', 50)->nullable()->after('account_wo');
            $table->date('completion_date')->nullable()->after('du_status');
            $table->text('du_remarks')->nullable()->after('completion_date');
            $table->text('additional_note')->nullable()->after('du_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropForeign(['executive_id']);
            $table->dropColumn([
                'call_verification', 'pending_from_sales', 'documents_verification',
                'submission_date_from', 'back_office_notes', 'activity', 'back_office_account',
                'work_order', 'du_status', 'completion_date', 'du_remarks', 'additional_note',
            ]);
        });
    }
};
