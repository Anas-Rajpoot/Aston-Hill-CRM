<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'occurred_at', 'user_id', 'user_name', 'user_role',
        'action', 'module', 'record_id', 'record_ref',
        'result', 'ip', 'user_agent', 'device', 'session_id',
        'route', 'method', 'latency_ms',
        'old_values', 'new_values', 'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'old_values'  => 'array',
        'new_values'  => 'array',
        'meta'        => 'array',
        'latency_ms'  => 'integer',
    ];

    /* ── Color helpers for the UI ── */

    public const ACTION_COLORS = [
        'created'         => 'green',
        'updated'         => 'blue',
        'deleted'         => 'red',
        'assigned'        => 'purple',
        'login'           => 'teal',
        'logout'          => 'gray',
        'password_reset'  => 'orange',
        'export'          => 'indigo',
        'error'           => 'red',
        'access_denied'   => 'red',
        'sla_change'      => 'yellow',
        'exported_data'   => 'indigo',
    ];

    /* ── Scopes ── */

    public function scopeBetweenDates($q, ?string $from, ?string $to)
    {
        if ($from) $q->where('occurred_at', '>=', $from);
        if ($to)   $q->where('occurred_at', '<=', $to . ' 23:59:59');
        return $q;
    }

    public function scopeOfUser($q, ?string $name)
    {
        return $name ? $q->where('user_name', 'like', "%{$name}%") : $q;
    }

    public function scopeOfRole($q, ?string $role)
    {
        return $role ? $q->where('user_role', $role) : $q;
    }

    public function scopeOfModule($q, ?string $module)
    {
        return $module && $module !== 'All Modules' ? $q->where('module', $module) : $q;
    }

    public function scopeOfAction($q, ?string $action)
    {
        return $action && $action !== 'All Actions' ? $q->where('action', $action) : $q;
    }

    public function scopeOfResult($q, ?string $result)
    {
        return $result && $result !== 'All Statuses' ? $q->where('result', $result) : $q;
    }

    public function scopeOfIp($q, ?string $ip)
    {
        return $ip ? $q->where('ip', 'like', "%{$ip}%") : $q;
    }

    public function scopeOfSession($q, ?string $sid)
    {
        return $sid ? $q->where('session_id', 'like', "%{$sid}%") : $q;
    }

    public function scopeOfDevice($q, ?string $device)
    {
        return $device ? $q->where('device', 'like', "%{$device}%") : $q;
    }

    public function scopeSearch($q, ?string $search)
    {
        if (! $search) return $q;
        return $q->where(fn ($w) => $w
            ->where('user_name', 'like', "%{$search}%")
            ->orWhere('record_ref', 'like', "%{$search}%")
            ->orWhere('module', 'like', "%{$search}%")
        );
    }

    /* ── Relationship ── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
