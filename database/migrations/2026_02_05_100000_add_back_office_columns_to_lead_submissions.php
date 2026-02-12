<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'lead_submissions';

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'executive_id')) {
                $table->foreignId('executive_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn($tableName, 'call_verification')) {
                $table->string('call_verification', 50)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'pending_from_sales')) {
                $table->string('pending_from_sales', 50)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'documents_verification')) {
                $table->string('documents_verification', 50)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'submission_date_from')) {
                $table->date('submission_date_from')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'back_office_notes')) {
                $table->text('back_office_notes')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'activity')) {
                $table->string('activity', 255)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'back_office_account')) {
                $table->string('back_office_account', 100)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'work_order')) {
                $table->string('work_order', 255)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'du_status')) {
                $table->string('du_status', 50)->nullable();
            }
            if (! Schema::hasColumn($tableName, 'completion_date')) {
                $table->date('completion_date')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'du_remarks')) {
                $table->text('du_remarks')->nullable();
            }
            if (! Schema::hasColumn($tableName, 'additional_note')) {
                $table->text('additional_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('lead_submissions', 'executive_id')) {
                $table->dropForeign(['executive_id']);
            }
            $cols = [
                'call_verification', 'pending_from_sales', 'documents_verification',
                'submission_date_from', 'back_office_notes', 'activity', 'back_office_account',
                'work_order', 'du_status', 'completion_date', 'du_remarks', 'additional_note',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('lead_submissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
