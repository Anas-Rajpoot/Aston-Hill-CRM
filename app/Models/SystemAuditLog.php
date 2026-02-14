<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Record an audit entry.
     *
     * @param string      $event     e.g. 'sla_rule.updated'
     * @param array|null  $oldValues Changed fields (old)
     * @param array|null  $newValues Changed fields (new)
     * @param int|null    $userId    Override auth user
     * @param string|null $entityType e.g. 'sla_rule'
     * @param int|null    $entityId   e.g. row id
     */
    public static function record(
        string $event,
        ?array $oldValues,
        ?array $newValues,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        return self::create([
            'user_id'     => $userId ?? auth()->id(),
            'event'       => $event,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => request()->ip(),
            'user_agent'  => substr((string) request()->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }
}
