<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('library_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->json('module_keys')->nullable();
            $table->json('tags')->nullable();
            $table->enum('visibility', ['public', 'internal', 'restricted'])->default('internal');
            $table->json('allowed_roles')->nullable();
            $table->json('allowed_departments')->nullable();
            $table->string('file_type')->index();
            $table->string('mime_type');
            $table->string('storage_disk')->default('public');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedBigInteger('last_version_id')->nullable();
            $table->string('checksum_sha256')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active')->index();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('archived_at')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('uploaded_by');
            $table->index('created_at');
        });

        Schema::create('library_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('library_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('change_note')->nullable();
            $table->string('storage_disk')->default('public');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'version']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_document_versions');
        Schema::dropIfExists('library_documents');
        Schema::dropIfExists('library_categories');
    }
};
