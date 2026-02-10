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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('account_number')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('pending');
            $table->string('service_type', 100)->nullable();
            $table->string('product_type', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('product_name', 200)->nullable();
            $table->string('mrc', 100)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('other', 500)->nullable();
            $table->string('migration_numbers', 100)->nullable();
            $table->string('fiber', 20)->nullable();
            $table->string('order_number', 100)->nullable();
            $table->string('wo_number', 100)->nullable();
            $table->date('completion_date')->nullable();
            $table->string('payment_connection', 100)->nullable();
            $table->string('contract_type', 100)->nullable();
            $table->date('contract_end_date')->nullable();
            $table->unsignedInteger('renewal_alert')->nullable();
            $table->text('additional_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_name', 'account_number']);
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
