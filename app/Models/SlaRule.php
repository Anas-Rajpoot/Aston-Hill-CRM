<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SlaRule extends Model
{
    public const CACHE_KEY = 'sla_rules';
    public const CACHE_TTL = 600; // 10 min

    protected $fillable = [
        'module_key',
        'module_name',
        'sla_duration_minutes',
        'warning_threshold_minutes',
        'notification_email',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'sla_duration_minutes'       => 'integer',
        'warning_threshold_minutes'  => 'integer',
        'is_active'                  => 'boolean',
    ];

    /** Get all rules (cached). */
    public static function cached(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::orderBy('id')->get();
        });
    }

    /** Clear cache after any update. */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Last updater relationship. */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Human-readable duration string, e.g. "8h (480 min)" or "1h 30m (90 min)".
     */
    public static function minutesToHuman(int $minutes): string
    {
        if ($minutes <= 0) return '0m (0 min)';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        $parts = [];
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0) $parts[] = $m . 'm';
        return implode(' ', $parts) . " ({$minutes} min)";
    }
}
