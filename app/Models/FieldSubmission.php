<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSubmission extends Model
{
    protected $fillable = [
        'client_id',
        'created_by',
        'company_name',
        'contact_number',
        'product',
        'alternate_number',
        'emirates',
        'location_coordinates',
        'complete_address',
        'additional_notes',
        'special_instruction',
        'manager_id',
        'team_leader_id',
        'sales_agent_id',
        'team_id',
        'field_executive_id',
        'meeting_date',
        'field_status',
        'remarks_by_field_agent',
        'status',
        'submitted_at',
    ];

    const STATUSES = ['draft', 'submitted'];

    /** Status options for field head edit (meeting/visit workflow). */
    const FIELD_STATUSES = [
        'Meeting Scheduled',
        'CM Cancelled',
        'Meeting Done - Closed Documents Shared with Sales',
        'Meeting Done - Closed CM will Share Documents',
        'Meeting Done - Sales In Follow Up',
        'Meeting Done - CM Not Interested',
        'Field Executive In Follow Up',
        'No Meeting Closed on Call',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'meeting_date' => 'date',
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

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class, 'team_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    public function fieldExecutive()
    {
        return $this->belongsTo(User::class, 'field_executive_id');
    }

    public function documents()
    {
        return $this->hasMany(FieldSubmissionDocument::class);
    }

    public function audits()
    {
        return $this->hasMany(FieldSubmissionAudit::class)->orderByDesc('changed_at');
    }

    /**
     * Visibility scope:
     * - Users with field_head.list see ALL submissions.
     * - Field agents see ALL submissions (they process assigned + unassigned).
     * - Other users see submissions they created, are assigned to, or belong to their team hierarchy.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->can('field_head.list')) {
            return $query;
        }

        // Field roles see ALL field submissions (assigned + unassigned)
        if ($user->hasRole('field_agent') || $user->hasRole('field_executive') || $user->hasRole('field')) {
            return $query;
        }

        \App\Services\TeamHierarchyService::scopeSubmissionsForUser(
            $query,
            $user,
            ['field_executive_id']
        );

        return $query;
    }
}
