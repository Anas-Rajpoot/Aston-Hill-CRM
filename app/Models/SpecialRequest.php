<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialRequest extends Model
{
    protected $fillable = [
        'created_by',
        'company_name',
        'account_number',
        'request_type',
        'status',
        'complete_address',
        'special_instruction',
        'manager_id',
        'team_leader_id',
        'sales_agent_id',
        'submitted_at',
    ];

    const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    const REQUEST_TYPES = [
        'General',
        'Support',
        'Relocation',
        'Renewal',
        'Other',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function salesAgent()
    {
        return $this->belongsTo(User::class, 'sales_agent_id');
    }

    public function documents()
    {
        return $this->hasMany(SpecialRequestDocument::class);
    }

    public function audits()
    {
        return $this->hasMany(SpecialRequestAudit::class)->orderByDesc('changed_at');
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasRole('superadmin')) {
            return $query;
        }

        \App\Services\TeamHierarchyService::scopeSubmissionsForUser($query, $user);

        return $query;
    }
}
