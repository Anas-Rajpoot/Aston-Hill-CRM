<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemPreference extends Model
{
    protected $fillable = [
        'timezone',
        'default_dashboard_landing_page',
        'default_table_page_size',
        'auto_refresh_dashboard',
        'auto_refresh_interval_minutes',
        'auto_save_draft_forms',
        'session_warning_before_logout',
        'session_warning_minutes',
        'updated_by',
    ];

    protected $casts = [
        'default_table_page_size' => 'integer',
        'auto_refresh_dashboard' => 'boolean',
        'auto_refresh_interval_minutes' => 'integer',
        'auto_save_draft_forms' => 'boolean',
        'session_warning_before_logout' => 'boolean',
        'session_warning_minutes' => 'integer',
    ];

    /** Cache key for the singleton row. */
    public const CACHE_KEY = 'system_prefs';
    public const CACHE_TTL = 600; // 10 minutes

    /** Default values (used by seeder and reset). */
    public const DEFAULTS = [
        'timezone' => 'Asia/Dubai',
        'default_dashboard_landing_page' => 'dashboard',
        'default_table_page_size' => 25,
        'auto_refresh_dashboard' => false,
        'auto_refresh_interval_minutes' => 5,
        'auto_save_draft_forms' => true,
        'session_warning_before_logout' => true,
        'session_warning_minutes' => 5,
    ];

    /** Allowed enums. */
    public const LANDING_PAGES = [
        'dashboard',
        'submissions',
        'lead-submissions',
        'field-submissions',
        'customer-support',
        'vas-requests',
        'clients',
        'order-status',
        'dsp-tracker',
        'verifiers-detail',
        'employees',
        'cisco-extensions',
        'attendance-log',
        'expenses',
        'personal-notes',
        'email-followups',
        'reports',
        'settings',
    ];
    public const PAGE_SIZES = [10, 25, 50, 100];

    /**
     * Get the singleton row (id = 1), cached.
     */
    public static function singleton(): self
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::firstOrCreate(['id' => 1], self::DEFAULTS);
        });
    }

    /** Clear the singleton cache. */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Relationship: last updater. */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
