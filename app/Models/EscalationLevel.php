<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

/**
 * Escalation levels for SLA breach notifications.
 *
 * Resolution logic per level:
 *   1) recipient_type holds a Spatie role slug (e.g. 'superadmin', 'manager', 'sales_agent')
 *   2) custom_email (nullable) — if set, escalation email goes to this address
 *   3) If custom_email is blank → resolve all users who have the role in recipient_type
 *   4) is_active toggle enables/disables this level for escalation processing
 */
class EscalationLevel extends Model
{
    public const CACHE_KEY = 'escalation_levels';
    public const CACHE_TTL = 300; // 5 min

    protected $fillable = [
        'level',
        'recipient_type',   // role slug (e.g. 'superadmin', 'manager', 'back_office')
        'custom_email',     // optional override email for this level
        'is_active',
    ];

    protected $casts = [
        'level'     => 'integer',
        'is_active' => 'boolean',
    ];

    // ── Cached access ────────────────────────────────────────
    public static function cached()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::orderBy('level')->get();
        });
    }

    public static function activeLevels()
    {
        return self::cached()->where('is_active', true)->values();
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ── Default seed data ────────────────────────────────────
    public static function defaults(): array
    {
        return [
            ['level' => 1, 'recipient_type' => 'sales_agent', 'is_active' => true],
            ['level' => 2, 'recipient_type' => 'manager',     'is_active' => true],
            ['level' => 3, 'recipient_type' => 'superadmin',  'is_active' => true],
        ];
    }

    /**
     * Get all available roles from the system for the dropdown.
     */
    public static function availableRoles(): array
    {
        return Role::orderBy('name')
            ->pluck('name')
            ->values()
            ->toArray();
    }

    // ── Resolve recipient email for a submission context ─────
    /**
     * Resolve the email address(es) for this escalation level.
     *
     * If custom_email is set → use it exclusively.
     * Otherwise → find all active users with the role in recipient_type.
     *
     * @param  object|null  $submission  (unused now but kept for future context)
     * @return string[]     Array of resolved email addresses
     */
    public function resolveRecipients($submission = null): array
    {
        // If a custom email is set, use it exclusively
        if (!empty($this->custom_email)) {
            return [$this->custom_email];
        }

        // Resolve users by the role slug stored in recipient_type
        return $this->resolveByRole($this->recipient_type);
    }

    /**
     * Find all active users with the given role and return their emails.
     */
    protected function resolveByRole(string $roleSlug): array
    {
        return User::role($roleSlug)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('status', 'active')
                  ->orWhereNull('status');
            })
            ->pluck('email')
            ->filter()
            ->values()
            ->toArray();
    }

    // ── Human-readable label ─────────────────────────────────
    public function getRecipientLabelAttribute(): string
    {
        $role = Role::where('name', $this->recipient_type)->first();
        $label = $role
            ? ucwords(str_replace('_', ' ', $role->name))
            : ucwords(str_replace('_', ' ', $this->recipient_type));

        if (!empty($this->custom_email)) {
            $label .= " → {$this->custom_email}";
        }

        return $label;
    }
}
