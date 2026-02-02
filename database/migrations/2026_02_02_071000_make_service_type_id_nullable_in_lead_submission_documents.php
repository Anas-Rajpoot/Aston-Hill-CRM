<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->dropForeign(['service_type_id']);
            $table->unsignedBigInteger('service_type_id')->nullable()->change();
            $table->foreign('service_type_id')->references('id')->on('service_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $table) {
            $table->dropForeign(['service_type_id']);
            $table->unsignedBigInteger('service_type_id')->nullable(false)->change();
            $table->foreign('service_type_id')->references('id')->on('service_types')->cascadeOnDelete();
        });
    }
};
