<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Contact Details tab: contact persons.
     */
    public function up(): void
    {
        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('name', 200)->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->string('alternate_number', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('as_updated_or_not', 20)->nullable();
            $table->date('as_expiry_date')->nullable();
            $table->text('additional_note')->nullable();
            $table->timestamps();
        });

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->index(['client_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
    }
};
