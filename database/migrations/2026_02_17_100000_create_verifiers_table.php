<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifiers', function (Blueprint $table) {
            $table->id();
            $table->string('verifier_name', 255)->nullable()->index();
            $table->string('verifier_number', 100)->nullable()->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifiers');
    }
};
