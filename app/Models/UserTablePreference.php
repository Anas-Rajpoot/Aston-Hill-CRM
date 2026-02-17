<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class UserTablePreference extends Model
{
    protected $fillable = ['user_id', 'module', 'per_page'];
    protected $casts    = ['per_page' => 'integer'];

    public const ALLOWED = [10, 20, 25, 50, 100];
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Resolve per_page for a user + module.
     * Priority: user pref → global default from system_preferences.
     */
    public static function resolve(int $userId, string $module): int
    {
        $key = "table_pref_{$userId}_{$module}";

        return Cache::remember($key, self::CACHE_TTL, function () use ($userId, $module) {
            $pref = self::where('user_id', $userId)->where('module', $module)->value('per_page');
            if ($pref) return $pref;

            // Fallback to global default
            return SystemPreference::singleton()->default_table_page_size ?? 25;
        });
    }

    /**
     * Set a user's per-module preference.
     */
    public static function setPreference(int $userId, string $module, int $perPage): self
    {
        $pref = self::updateOrCreate(
            ['user_id' => $userId, 'module' => $module],
            ['per_page' => $perPage]
        );

        Cache::forget("table_pref_{$userId}_{$module}");

        return $pref;
    }

    public function user() { return $this->belongsTo(User::class); }
}
