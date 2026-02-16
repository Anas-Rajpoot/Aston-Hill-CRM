<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VasRequestAudit extends Model
{
    protected $table = 'vas_request_audits';

    protected $fillable = [
        'vas_request_submission_id',
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

    public function vasRequestSubmission()
    {
        return $this->belongsTo(VasRequestSubmission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
