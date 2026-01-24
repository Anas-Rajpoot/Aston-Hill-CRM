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
        Schema::create('email_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->date('email_date')->index();
            $table->string('subject', 255);
            $table->string('category', 100)->index();

            $table->string('request_from', 190)->nullable()->index();
            $table->string('sent_to', 190)->nullable()->index();

            $table->text('comment')->nullable();

            $table->index(['created_by', 'email_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_follow_ups');
    }
};
