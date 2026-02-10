<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Contact Details tab: addresses.
     */
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->text('full_address')->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('building', 200)->nullable();
            $table->string('area', 200)->nullable();
            $table->string('emirates', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('client_addresses', function (Blueprint $table) {
            $table->index(['client_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
