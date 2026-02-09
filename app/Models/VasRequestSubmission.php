<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VasRequestSubmission extends Model
{
    protected $table = 'vas_request_submissions';

    protected $fillable = [
        'account_number',
        'company_name',
        'request_type',
        'description',
        'status',
        'sales_agent_id',
        'team_leader_id',
        'manager_id',
        'back_office_executive_id',
        'created_by',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    const STATUSES = ['draft', 'submitted'];

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
}
