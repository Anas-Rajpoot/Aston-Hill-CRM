<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VasRequestSubmission extends Model
{
    protected $table = 'vas_request_submissions';

    protected $fillable = [
        'account_number',
        'contact_number',
        'company_name',
        'request_type',
        'request_description',
        'additional_notes',
        'status',
        'sales_agent_id',
        'team_leader_id',
        'manager_id',
        'team_id',
        'back_office_executive_id',
        'created_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    const STATUSES = ['draft', 'submitted', 'approved', 'rejected'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manager() {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamLeader() {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function salesAgent() {
        return $this->belongsTo(User::class, 'sales_agent_id');
    }

    public function documents()
    {
        return $this->hasMany(VasRequestDocument::class, 'vas_request_submission_id');
    }

    public function backOfficeExecutive() {
        return $this->belongsTo(User::class, 'back_office_executive_id');
    }

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class, 'team_id');
    }

    /**
     * Apply RBAC visibility scope:
     *  - Super Admin / vas.view.all → ALL records
     *  - Back Office                → ALL records (they process assigned + unassigned)
     *  - Manager                    → own + team hierarchy members' records
     *  - Team Leader                → own + direct team members' records
     *  - Sales Agent                → only assigned-to or created-by them
     */
    public function scopeVisibleTo($q, User $user)
    {
        if ($user->can('vas.view.all')) {
            return $q;
        }

        // Back office sees ALL VAS request submissions (assigned + unassigned)
        if ($user->hasRole('back_office')) {
            return $q;
        }

        \App\Services\TeamHierarchyService::scopeSubmissionsForUser($q, $user, ['back_office_executive_id']);

        return $q;
    }
}
