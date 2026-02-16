<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSubmission extends Model
{
    protected $fillable = [
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
        'Meeting Scheduled', 'Visited', 'Cancelled', 'Rescheduled', 'No Show',
        'Pending Assignment', 'Site Survey Scheduled', 'Survey Completed', 'In Progress', 'Installation Scheduled', 'Completed',
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
     * - Other users see submissions they created or are assigned to (as field executive,
     *   sales agent, team leader, or manager).
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->can('field_head.list')) {
            return $query;
        }

        $userId = (int) $user->id;

        return $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhere('field_executive_id', $userId)
              ->orWhere('sales_agent_id', $userId)
              ->orWhere('team_leader_id', $userId)
              ->orWhere('manager_id', $userId);
        });
    }
}
