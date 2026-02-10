<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CiscoExtension extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_NOT_CREATED = 'not_created';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_NOT_CREATED];

    protected $fillable = [
        'extension',
        'landline_number',
        'gateway',
        'username',
        'password',
        'status',
        'assigned_to',
        'team_leader_id',
        'manager_id',
        'comment',
    ];

    public function audits()
    {
        return $this->hasMany(CiscoExtensionAudit::class)->orderByDesc('created_at');
    }

    protected $hidden = [
        'password',
    ];

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function getUsageAttribute(): string
    {
        return $this->assigned_to ? 'assigned' : 'unassigned';
    }
}
