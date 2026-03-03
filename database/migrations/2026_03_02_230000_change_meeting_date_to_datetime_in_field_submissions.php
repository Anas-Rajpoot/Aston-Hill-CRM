<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dateTime('meeting_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->date('meeting_date')->nullable()->change();
        });
    }
};
