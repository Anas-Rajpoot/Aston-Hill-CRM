<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tracks client field changes: old value, new value, time, person (like field submission).
     */
    public function up(): void
    {
        Schema::create('client_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('field_name', 80);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('changed_at');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('client_audits', function (Blueprint $table) {
            $table->index(['client_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_audits');
    }
};
