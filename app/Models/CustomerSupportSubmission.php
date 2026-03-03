<?php

namespace App\Models;

use App\Traits\ResolvesClientLink;
use App\Support\RbacPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CustomerSupportSubmission extends Model
{
    use ResolvesClientLink;

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            if (empty($submission->ticket_number)) {
                $submission->ticket_number = static::generateTicketNumber();
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        $today = now();
        $prefix = 'AH' . $today->format('dmY');

        $count = DB::table('customer_support_submissions')
            ->whereDate('created_at', $today->toDateString())
            ->count();

        $seq = str_pad($count + 1, 2, '0', STR_PAD_LEFT);

        return $prefix . $seq;
    }

    protected $table = 'customer_support_submissions';

    protected $fillable = [
        'client_id',
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
        'team_id',
        'csr_id',
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

    const STATUSES = [
        'draft',
        'submitted',
        'approved',
        'rejected',
        'completed',
        'Pending with CM',
        'Pending with DU',
        'Pending with Sales',
        'Pending with CSR',
    ];

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

    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class, 'team_id');
    }

    public function csrUser()
    {
        return $this->belongsTo(User::class, 'csr_id');
    }

    /**
     * Apply RBAC visibility scope:
     *  - Super Admin                         → ALL records
     *  - CSR (customer_support_representative) → ALL records (they process assigned + unassigned)
     *  - Support Manager                     → ALL records
     *  - Manager                             → own + team hierarchy members' records
     *  - Team Leader                         → own + direct team members' records
     *  - Sales Agent                         → only assigned-to or created-by them
     */
    public function scopeVisibleTo($q, User $user)
    {
        // Users with explicit module read access can see all customer support submissions.
        if (RbacPermission::can($user, 'customer_support_requests', 'read', [
            'customer_support_requests.list',
            'customer_support_requests.view',
        ])) {
            return $q;
        }

        // CSR and support managers see ALL customer support submissions
        if ($user->hasRole('customer_support_representative') || $user->hasRole('support_manager') || $user->hasRole('csr')) {
            return $q;
        }

        \App\Services\TeamHierarchyService::scopeSubmissionsForUser($q, $user, ['csr_id']);

        return $q;
    }
}
