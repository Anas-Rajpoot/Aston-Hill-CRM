<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Company Details tab: trade license, establishment card, account mapping, bills, etc.
     */
    public function up(): void
    {
        Schema::create('client_company_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('trade_license_issuing_authority', 200)->nullable();
            $table->string('company_category', 100)->nullable();
            $table->string('trade_license_number', 100)->nullable();
            $table->date('trade_license_expiry_date')->nullable();
            $table->string('establishment_card_number', 100)->nullable();
            $table->date('establishment_card_expiry_date')->nullable();
            $table->string('account_taken_from', 100)->nullable();
            $table->date('account_mapping_date')->nullable();
            $table->string('account_transfer_given_to', 200)->nullable();
            $table->date('account_transfer_given_date')->nullable();
            $table->string('account_manager_name', 200)->nullable();
            $table->string('csr_name_1', 100)->nullable();
            $table->string('csr_name_2', 100)->nullable();
            $table->string('csr_name_3', 100)->nullable();
            $table->string('first_bill', 50)->nullable(); // Paid / Unpaid
            $table->string('second_bill', 50)->nullable();
            $table->string('third_bill', 50)->nullable();
            $table->string('fourth_bill', 50)->nullable();
            $table->text('additional_comment_1')->nullable();
            $table->text('additional_comment_2')->nullable();
            $table->timestamps();
        });

        Schema::table('client_company_details', function (Blueprint $table) {
            $table->unique('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_company_details');
    }
};
