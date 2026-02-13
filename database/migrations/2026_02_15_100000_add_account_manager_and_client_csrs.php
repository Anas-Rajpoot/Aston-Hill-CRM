<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Account Manager & CSR section: account_manager_id on clients, client_csrs for multiple CSRs.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('account_manager_id')->nullable()->after('sales_agent_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('client_csrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('client_csrs', function (Blueprint $table) {
            $table->index(['client_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_csrs');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['account_manager_id']);
        });
    }
};
