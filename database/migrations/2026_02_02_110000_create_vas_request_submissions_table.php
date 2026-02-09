<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vas_request_submissions', function (Blueprint $table) {
            $table->id();

            $table->string('account_number')->nullable();
            $table->string('company_name')->nullable();
            $table->string('request_type');
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                  ->default('draft');

            $table->foreignId('sales_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('back_office_executive_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vas_request_submissions');
    }
};
