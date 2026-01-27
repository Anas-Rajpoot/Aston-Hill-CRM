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
        Schema::create('lead_submission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('doc_key')->nullable();
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['lead_submission_id','doc_key']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_submission_documents');
    }
};
