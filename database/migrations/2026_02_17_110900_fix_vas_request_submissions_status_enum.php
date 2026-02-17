<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE vas_request_submissions MODIFY COLUMN status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vas_request_submissions MODIFY COLUMN status ENUM('draft','submitted') NOT NULL DEFAULT 'draft'");
    }
};
