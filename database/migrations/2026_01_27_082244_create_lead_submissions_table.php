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
        Schema::create('lead_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            $table->enum('status', ['draft','submitted','approved','rejected'])
                    ->default('draft');
            $table->string('account_number')->nullable();
            $table->string('company_name')->nullable();
            $table->string('authorized_signatory_name')->nullable();
            $table->string('contact_number_gsm')->nullable();
            $table->string('alternate_contact_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emirate')->nullable();
            $table->string('location_coordinates')->nullable();
            $table->string('product')->nullable();
            $table->text('offer')->nullable();
            $table->decimal('mrc_aed', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('sales_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->foreignId('rejected_by')->nullable();
            $table->index(['status','created_at']);
            $table->index(['service_category_id','service_type_id']);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_submissions');
    }
};
