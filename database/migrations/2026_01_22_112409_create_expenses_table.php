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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('expense_date');

            $table->string('product_category', 190);
            $table->string('invoice_number', 190)->nullable()->index();

            $table->text('product_description')->nullable();
            $table->text('comment')->nullable();

            // VAT as rate (e.g. 21.00)
            $table->decimal('vat_amount', 8, 2)->nullable();

            // amount without VAT (net)
            $table->decimal('amount_without_vat', 12, 2)->default(0);
            $table->decimal('full_amount', 12, 2)->default(0);
            $table->index(['expense_date']);
            $table->index(['product_category']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
