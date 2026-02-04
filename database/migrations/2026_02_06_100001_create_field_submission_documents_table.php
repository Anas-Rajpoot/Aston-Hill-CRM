<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_submission_id')->constrained('field_submissions')->cascadeOnDelete();
            $table->string('doc_key', 80)->default('photographic_proof');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('label', 120)->nullable();
            $table->string('mime', 60)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_submission_documents');
    }
};
