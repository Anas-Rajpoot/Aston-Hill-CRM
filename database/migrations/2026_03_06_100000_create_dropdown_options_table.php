<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('group', 100)->index();        // e.g. 'lead_statuses', 'emirates', 'service_categories'
            $table->string('value', 255);                  // the actual value stored in records
            $table->string('label', 255)->nullable();      // display label (defaults to value)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropdown_options');
    }
};
