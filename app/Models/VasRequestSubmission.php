<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VasRequestSubmission extends Model
{
    protected $table = 'vas_request_submissions';

    protected $fillable = [
        'created_by',
        'request_type',
        'account_number',
        'contact_number',
        'company_name',
        'request_description',
        'additional_notes',
        'manager_id',
        'team_leader_id',
        'sales_agent_id',
        'status',
        'submitted_at',
    ];

    const STATUSES = ['draft', 'submitted'];

    protected $casts = [
        'submitted_at' => 'datetime',
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
        return $this->hasMany(VasRequestDocument::class, 'vas_request_submission_id');
    }
}
