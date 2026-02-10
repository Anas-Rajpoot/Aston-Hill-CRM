<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Alerts tab: trade license expiry, establishment card expiry, etc.
     */
    public function up(): void
    {
        Schema::create('client_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('alert_type', 80); // e.g. trade_license_expiry, establishment_card_expiry
            $table->string('company_name', 200)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('days_remaining')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->nullable(); // Valid, Invalid, ACTIVE, etc.
            $table->timestamp('status_date')->nullable();
            $table->date('created_date')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('client_alerts', function (Blueprint $table) {
            $table->index(['client_id', 'alert_type', 'resolved']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_alerts');
    }
};
