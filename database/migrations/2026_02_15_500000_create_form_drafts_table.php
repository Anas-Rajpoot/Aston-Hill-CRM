<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 80);           // e.g. 'lead-submission', 'client', 'announcement'
            $table->string('record_ref', 120);       // record ID or client-side UUID for "new"
            $table->json('data');                     // draft payload (only changed fields)
            $table->timestamps();

            $table->unique(['user_id', 'module', 'record_ref']);
            $table->index('updated_at');             // for expiry purge
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_drafts');
    }
};
