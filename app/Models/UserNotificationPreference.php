<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Per-user, per-trigger, per-channel notification preference.
 *
 * Resolution order:
 *   1) Channel OFF → false (regardless of user pref)
 *   2) User pref exists → use it
 *   3) Fallback → trigger's default (email_enabled / in_app_enabled / email_alert_enabled)
 */
class UserNotificationPreference extends Model
{
    protected $fillable = ['user_id', 'trigger_id', 'channel', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    // ── Relationships ────────────────────────────────────────
    public function user()    { return $this->belongsTo(User::class); }
    public function trigger() { return $this->belongsTo(NotificationTrigger::class, 'trigger_id'); }

    // ── Lookup ───────────────────────────────────────────────
    public static function forUser(int $userId): array
    {
        return Cache::remember("user_{$userId}_notif_prefs", 60, function () use ($userId) {
            return self::where('user_id', $userId)
                ->get()
                ->groupBy(fn ($p) => $p->trigger_id . '_' . $p->channel)
                ->map(fn ($group) => $group->first()->enabled)
                ->toArray();
        });
    }

    /**
     * Set a user preference. Returns the upserted record.
     */
    public static function setPreference(int $userId, int $triggerId, string $channel, bool $enabled): self
    {
        $pref = self::updateOrCreate(
            ['user_id' => $userId, 'trigger_id' => $triggerId, 'channel' => $channel],
            ['enabled' => $enabled],
        );

        self::clearUserCache($userId);
        return $pref;
    }

    /**
     * Reset all user preferences for a given channel.
     */
    public static function resetChannel(int $userId, string $channel): int
    {
        $count = self::where('user_id', $userId)->where('channel', $channel)->delete();
        self::clearUserCache($userId);
        return $count;
    }

    public static function clearUserCache(int $userId): void
    {
        Cache::forget("user_{$userId}_notif_prefs");
    }
}
