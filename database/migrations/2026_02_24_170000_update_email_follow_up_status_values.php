<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_follow_ups')
            ->where('status', 'followed_up')
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        DB::table('email_follow_ups')
            ->where('status', 'approved')
            ->update(['status' => 'followed_up']);
    }
};

