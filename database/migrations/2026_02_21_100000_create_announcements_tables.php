<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the existing announcements table with full Announcement Center columns.
 * Existing columns: id, created_by, title, body, attachment_*, is_pinned, is_active, published_at, timestamps
 * New columns:      type, link_url, link_label, priority, all_users, audiences, channels,
 *                   require_ack, ack_due_at, expire_at, archived_at, updated_by
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('type', 10)->default('text')->after('title');
            $table->string('link_url', 2048)->nullable()->after('body');
            $table->string('link_label', 80)->nullable()->after('link_url');
            $table->string('priority', 10)->default('normal')->after('link_label')->index();
            $table->boolean('all_users')->default(true)->after('priority');
            $table->json('audiences')->nullable()->after('all_users');
            $table->json('channels')->nullable()->after('audiences');
            $table->boolean('require_ack')->default(false)->after('is_active');
            $table->dateTime('ack_due_at')->nullable()->after('require_ack');
            $table->dateTime('expire_at')->nullable()->after('published_at')->index();
            $table->dateTime('archived_at')->nullable()->after('expire_at')->index();
            $table->foreignId('updated_by')->nullable()->after('created_by');
        });

        // Acknowledgement tracking
        Schema::create('announcement_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('acknowledged_at');
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_acknowledgements');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'type', 'link_url', 'link_label', 'priority', 'all_users',
                'audiences', 'channels', 'require_ack', 'ack_due_at',
                'expire_at', 'archived_at', 'updated_by',
            ]);
        });
    }
};
