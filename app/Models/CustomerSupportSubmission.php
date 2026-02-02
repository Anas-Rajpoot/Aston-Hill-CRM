<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSupportSubmission extends Model
{
    protected $table = 'customer_support_submissions';

    protected $fillable = [
        'created_by',
        'issue_category',
        'company_name',
        'account_number',
        'contact_number',
        'issue_description',
        'attachments',
        'manager_id',
        'team_leader_id',
        'sales_agent_id',
        'status',
        'submitted_at',
    ];

    const STATUSES = ['draft', 'submitted'];

    protected $casts = [
        'attachments' => 'array',
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
}
