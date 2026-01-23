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
        Schema::create('personal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title', 190);
            $table->text('body')->nullable();

            // todo fields
            $table->enum('status', ['pending','done'])->default('pending');
            $table->enum('priority', ['low','medium','high'])->default('medium');

            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id','status']);
            $table->index(['user_id','due_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_notes');
    }
};
