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
        Schema::create('field_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('contact_number');
            $table->string('product');
            $table->string('alternate_number');
            $table->string('emirates');
            $table->string('location_coordinates')->nullable();
            $table->text('complete_address');
            $table->text('additional_notes')->nullable();
            $table->text('special_instruction')->nullable();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sales_agent_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_submissions');
    }
};
