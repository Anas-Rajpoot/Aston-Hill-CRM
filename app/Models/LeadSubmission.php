<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSubmission extends Model
{
    protected $fillable = [
        'created_by','updated_by','step','status','status_changed_at',
        'account_number','company_name','authorized_signatory_name','contact_number_gsm',
        'alternate_contact_number','email','address','emirate','location_coordinates',
        'product','offer','mrc_aed','quantity','ae_domain','gaid',
        'sales_agent_id','team_leader_id','manager_id','executive_id','service_category_id','service_type_id',
        'payload','submitted_at','remarks','approved_at','rejected_at','approved_by','rejected_by',
        'call_verification','pending_from_sales','documents_verification','submission_date_from',
        'back_office_notes','activity','back_office_account','work_order','du_status','completion_date','du_remarks','additional_note',
    ];

    const STATUSES = ['draft','submitted','approved','rejected','pending_for_ata','pending_for_finance','pending_from_sales','unassigned'];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'submission_date_from' => 'date',
        'completion_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (LeadSubmission $lead) {
            if ($lead->isDirty('status')) {
                $lead->status_changed_at = now();
            }
        });
    }

    public function submit()
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'status_changed_at' => now(),
        ]);
    }

    public function approve($userId)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'status_changed_at' => now(),
        ]);
    }

    public function reject($userId)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'status_changed_at' => now(),
        ]);
    }

    public function documents()
    { 
        return $this->hasMany(LeadSubmissionDocument::class); 
    }
    public function category()
    { 
        return $this->belongsTo(ServiceCategory::class,'service_category_id'); 
    }
    public function type()
    { 
        return $this->belongsTo(ServiceType::class,'service_type_id'); 
    }

    public function creator()
    {
        return $this->belongsTo(User::class,'created_by');
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

    public function executive()
    {
        return $this->belongsTo(User::class, 'executive_id');
    }

    /** Apply visibility based on user permissions (view.all | view.assigned | view.created). */
    public function scopeVisibleTo($q, User $user)
    {
        if ($user->can('lead.view.all')) {
            return $q;
        }
        $userId = (int) $user->id;
        $hasCreated = $user->can('lead.view.created');
        $hasAssigned = $user->can('lead.view.assigned');
        if (!$hasCreated && !$hasAssigned) {
            return $q->whereRaw('1 = 0');
        }
        $q->where(function ($w) use ($hasCreated, $hasAssigned, $userId) {
            if ($hasCreated) {
                $w->orWhere('created_by', $userId);
            }
            if ($hasAssigned) {
                $w->orWhere('sales_agent_id', $userId)
                    ->orWhere('team_leader_id', $userId)
                    ->orWhere('manager_id', $userId);
            }
        });
        return $q;
    }

    public function scopeFilter($q, array $f)
    {
        return $q
            ->when(!empty($f['q']), fn($qq)=>$qq->where(function($w) use ($f){
                $term = $f['q'];
                $w->where('company_name','like',"%$term%")
                  ->orWhere('account_number','like',"%$term%")
                  ->orWhere('email','like',"%$term%");
            }))
            ->when(!empty($f['status']), fn($qq)=>$qq->where('status',$f['status']))
            ->when(!empty($f['category']), fn($qq)=>$qq->where('service_category_id',$f['category']))
            ->when(!empty($f['type']), fn($qq)=>$qq->where('service_type_id',$f['type']))
            ->when(!empty($f['from']), fn($qq)=>$qq->whereDate('created_at','>=',$f['from']))
            ->when(!empty($f['to']), fn($qq)=>$qq->whereDate('created_at','<=',$f['to']));
    }
}

