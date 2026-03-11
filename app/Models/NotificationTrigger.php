<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class NotificationTrigger extends Model
{
    protected $fillable = [
        'key', 'name', 'module',
        'website_enabled', 'email_enabled', 'in_app_enabled',
        'email_alert_enabled', 'is_active',
        'updated_by',
    ];

    protected $casts = [
        'website_enabled'     => 'boolean',
        'email_enabled'       => 'boolean',
        'in_app_enabled'      => 'boolean',
        'email_alert_enabled' => 'boolean',
        'is_active'           => 'boolean',
    ];

    /**
     * Map: trigger column → channel key in notification_settings
     */
    public const COLUMN_TO_CHANNEL = [
        'email_enabled'       => 'email',
        'in_app_enabled'      => 'in_app',
        'email_alert_enabled' => 'sla_alerts',
    ];

    /**
     * Map: channel key → master toggle in notification_settings
     */
    public const CHANNEL_TO_MASTER = [
        'email'      => 'enable_email',
        'in_app'     => 'enable_web',
        'sla_alerts' => 'enable_sla_alerts',
    ];

    /**
     * Map: channel key → trigger default column
     */
    public const CHANNEL_TO_DEFAULT_COL = [
        'email'      => 'email_enabled',
        'in_app'     => 'in_app_enabled',
        'sla_alerts' => 'email_alert_enabled',
    ];

    // ── Relationships ────────────────────────────────────────
    public function preferences()
    {
        return $this->hasMany(UserNotificationPreference::class, 'trigger_id');
    }

    /**
     * Support both schema variants:
     * - notification_triggers.key
     * - notification_triggers.trigger_key
     */
    public static function triggerKeyColumn(): ?string
    {
        if (Schema::hasColumn('notification_triggers', 'key')) {
            return 'key';
        }
        if (Schema::hasColumn('notification_triggers', 'trigger_key')) {
            return 'trigger_key';
        }
        return null;
    }

    public static function hasTableColumn(string $column): bool
    {
        return Schema::hasColumn('notification_triggers', $column);
    }

    public static function findByTriggerKey(string $triggerKey): ?self
    {
        $col = self::triggerKeyColumn();
        if ($col) {
            return self::query()->where($col, $triggerKey)->first();
        }

        // Legacy fallback: match by humanized name when key column doesn't exist.
        $nameGuess = ucwords(str_replace('_', ' ', trim($triggerKey)));
        return self::query()->where('name', $nameGuess)->first();
    }

    public function resolvedTriggerKey(): string
    {
        $column = self::triggerKeyColumn();
        if ($column) {
            return (string) ($this->{$column} ?? '');
        }
        return strtolower(str_replace(' ', '_', (string) ($this->name ?? '')));
    }

    // ── Resolution: returns triggers with computed states for a user ──
    /**
     * Get all triggers with resolved enabled/locked state per channel for a given user.
     *
     * Resolution per channel:
     *   locked  = channel master switch is OFF
     *   enabled = locked ? false : (user_pref ?? trigger_default)
     */
    public static function allWithResolvedState(int $userId): array
    {
        $settings  = NotificationSetting::singleton();
        $triggers  = self::orderBy('id')->get();
        $userPrefs = UserNotificationPreference::forUser($userId);

        $channels = [
            'email'      => (bool) $settings->enable_email,
            'in_app'     => (bool) $settings->enable_web,
            'sla_alerts' => (bool) $settings->enable_sla_alerts,
        ];

        return $triggers->map(function ($trigger) use ($channels, $userPrefs) {
            $row = $trigger->toArray();

            foreach (self::COLUMN_TO_CHANNEL as $col => $channel) {
                $masterOn    = $channels[$channel] ?? false;
                $prefKey     = $trigger->id . '_' . $channel;
                $trigDefault = (bool) $trigger->{$col};

                // Resolved enabled: master must be ON, then check user pref, else trigger default
                $resolved = $masterOn
                    ? (isset($userPrefs[$prefKey]) ? (bool) $userPrefs[$prefKey] : $trigDefault)
                    : false;

                $row[$col]                  = $resolved;         // resolved value
                $row[$channel . '_locked']  = ! $masterOn;       // is this column locked?
                $row[$channel . '_default'] = $trigDefault;       // system default for reset
            }

            $row['is_active'] = (bool) $trigger->is_active;

            return $row;
        })->toArray();
    }

    /**
     * Check if a specific channel is enabled at the master level.
     *
     * For 'sla_alerts': also requires at least one active EscalationLevel.
     */
    public static function isChannelEnabled(string $channel): bool
    {
        $masterCol = self::CHANNEL_TO_MASTER[$channel] ?? null;
        if (! $masterCol) return false;

        $settings = NotificationSetting::singleton();
        $enabled  = (bool) $settings->{$masterCol};

        // SLA alerts additionally require active escalation levels
        if ($channel === 'sla_alerts' && $enabled) {
            $enabled = EscalationLevel::activeLevels()->isNotEmpty();
        }

        return $enabled;
    }
}
