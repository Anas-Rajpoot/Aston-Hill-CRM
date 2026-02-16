<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SecuritySetting extends Model
{
    public const CACHE_KEY = 'security_settings';
    public const CACHE_TTL = 600; // 10 minutes

    /**
     * Safe defaults — used by seeder and reset.
     */
    public const DEFAULTS = [
        'auto_logout_after_minutes'        => 120,
        'session_warning_minutes'          => 5,
        'force_logout_on_close'            => false,
        'prevent_multiple_sessions'        => false,
        'max_login_attempts'               => 5,
        'lock_after_failed_attempts'       => true,
        'lock_duration_minutes'            => 30,
        'force_password_reset_on_first_login' => true,
        'min_length'                       => 8,
        'require_uppercase'                => true,
        'require_number'                   => true,
        'require_special'                  => true,
        'password_expiry_days'             => 90,
    ];

    protected $fillable = [
        'auto_logout_after_minutes',
        'session_warning_minutes',
        'force_logout_on_close',
        'prevent_multiple_sessions',
        'max_login_attempts',
        'lock_after_failed_attempts',
        'lock_duration_minutes',
        'force_password_reset_on_first_login',
        'min_length',
        'require_uppercase',
        'require_number',
        'require_special',
        'password_expiry_days',
        'updated_by',
    ];

    protected $casts = [
        'auto_logout_after_minutes'        => 'integer',
        'session_warning_minutes'          => 'integer',
        'force_logout_on_close'            => 'boolean',
        'prevent_multiple_sessions'        => 'boolean',
        'max_login_attempts'               => 'integer',
        'lock_after_failed_attempts'       => 'boolean',
        'lock_duration_minutes'            => 'integer',
        'force_password_reset_on_first_login' => 'boolean',
        'min_length'                       => 'integer',
        'require_uppercase'                => 'boolean',
        'require_number'                   => 'boolean',
        'require_special'                  => 'boolean',
        'password_expiry_days'             => 'integer',
    ];

    /* ── Singleton accessor with caching ── */

    public static function current(): self
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::firstOrCreate(['id' => 1], self::DEFAULTS);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /* ── Relationships ── */

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
