<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('path', 500);
            $table->string('disk', 32)->default('local');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('type', 32)->nullable()->comment('invoice or supporting');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
    }
};
