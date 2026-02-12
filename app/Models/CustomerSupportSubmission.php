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
        'ticket_number',
        'csr_name',
        'workflow_status',
        'completion_date',
        'trouble_ticket',
        'activity',
        'pending',
        'resolution_remarks',
        'internal_remarks',
    ];

    const STATUSES = ['draft', 'submitted'];

    const WORKFLOW_STATUSES = ['open', 'in_progress', 'pending', 'resolved', 'closed'];

    const PENDING_OPTIONS = [
        'Pending with Sales',
        'Pending with CSR',
        'Pending with Customer',
        'Pending with Back Office',
    ];

    protected $casts = [
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'completion_date' => 'date',
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
