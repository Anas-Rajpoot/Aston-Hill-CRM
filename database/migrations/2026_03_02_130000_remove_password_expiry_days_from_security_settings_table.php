<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('security_settings')) {
            return;
        }

        if (Schema::hasColumn('security_settings', 'password_expiry_days')) {
            Schema::table('security_settings', function (Blueprint $table): void {
                $table->dropColumn('password_expiry_days');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('security_settings')) {
            return;
        }

        if (! Schema::hasColumn('security_settings', 'password_expiry_days')) {
            Schema::table('security_settings', function (Blueprint $table): void {
                $table->unsignedInteger('password_expiry_days')->default(90);
            });
        }
    }
};
