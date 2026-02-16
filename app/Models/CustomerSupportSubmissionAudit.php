<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSupportSubmissionAudit extends Model
{
    protected $table = 'customer_support_submission_audits';

    protected $fillable = [
        'customer_support_submission_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_at',
        'changed_by',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function customerSupportSubmission()
    {
        return $this->belongsTo(CustomerSupportSubmission::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
