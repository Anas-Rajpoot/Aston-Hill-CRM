<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSubmission extends Model
{
    protected $fillable = [
        'created_by','updated_by','step','status',
        'account_number','company_name','authorized_signatory_name','contact_number_gsm',
        'alternate_contact_number','email','address','emirate','location_coordinates',
        'product','offer','mrc_aed','quantity','sales_agent_id','team_leader_id',
        'manager_id','service_category_id','service_type_id','payload','submitted_at',
        'remarks','approved_at','rejected_at','approved_by','rejected_by'
    ];

    const STATUSES = ['draft','submitted','approved','rejected'];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function submit()
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approve($userId)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject($userId)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $userId,
            'rejected_at' => now(),
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

