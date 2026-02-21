<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->index('field_executive_id', 'fs_field_executive_id_idx');
        });

        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->index('executive_id', 'ls_executive_id_idx');
        });

        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->index('csr_id', 'css_csr_id_idx');
        });

        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->index('back_office_executive_id', 'vrs_bo_executive_id_idx');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->index(['model_id', 'model_type'], 'mhr_model_id_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('field_submissions', function (Blueprint $table) {
            $table->dropIndex('fs_field_executive_id_idx');
        });

        Schema::table('lead_submissions', function (Blueprint $table) {
            $table->dropIndex('ls_executive_id_idx');
        });

        Schema::table('customer_support_submissions', function (Blueprint $table) {
            $table->dropIndex('css_csr_id_idx');
        });

        Schema::table('vas_request_submissions', function (Blueprint $table) {
            $table->dropIndex('vrs_bo_executive_id_idx');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropIndex('mhr_model_id_type_idx');
        });
    }
};
