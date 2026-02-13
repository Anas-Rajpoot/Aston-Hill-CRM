<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsp_tracker_entries', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id', 36)->index();
            $table->string('activity_number', 100)->nullable()->index();
            $table->string('company_name', 255)->nullable()->index();
            $table->string('account_number', 100)->nullable();
            $table->string('request_type', 100)->nullable();
            $table->string('appointment_date', 50)->nullable();
            $table->string('appointment_time', 50)->nullable();
            $table->string('product', 255)->nullable();
            $table->string('so_number', 100)->nullable();
            $table->string('request_status', 100)->nullable()->index();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('verifier_name', 255)->nullable();
            $table->string('verifier_number', 100)->nullable();
            $table->string('dsp_om_id', 100)->nullable();
            $table->string('uploaded_by', 255)->nullable();
            $table->string('uploaded_at', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsp_tracker_entries');
    }
};
