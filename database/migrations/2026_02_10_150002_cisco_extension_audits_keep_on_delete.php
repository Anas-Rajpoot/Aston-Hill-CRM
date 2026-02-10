<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cisco_extension_audits', function (Blueprint $table) {
            $table->dropForeign(['cisco_extension_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cisco_extension_audits', function (Blueprint $table) {
            $table->foreign('cisco_extension_id')->references('id')->on('cisco_extensions')->cascadeOnDelete();
        });
    }
};
