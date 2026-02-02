<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vas_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vas_request_submission_id')->constrained('vas_request_submissions')->cascadeOnDelete();
            $table->string('doc_key'); // trade_license, establishment_card, etc.
            $table->string('file_path');
            $table->string('file_name');
            $table->string('label')->nullable(); // for additional documents
            $table->timestamps();
            $table->index(['vas_request_submission_id', 'doc_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vas_request_documents');
    }
};
