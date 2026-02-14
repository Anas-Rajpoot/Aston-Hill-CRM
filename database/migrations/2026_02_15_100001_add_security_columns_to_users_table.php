<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->datetime('password_changed_at')->nullable()->after('must_change_password');
            $table->datetime('locked_until')->nullable()->after('password_changed_at');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('locked_until');

            $table->index('locked_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locked_until']);
            $table->dropColumn(['must_change_password', 'password_changed_at', 'locked_until', 'failed_login_attempts']);
        });
    }
};
