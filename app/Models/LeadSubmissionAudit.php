<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadSubmissionAudit extends Model
{
    protected $table = 'lead_submission_audits';

    protected $fillable = [
        'lead_submission_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_at',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function leadSubmission()
    {
        return $this->belongsTo(LeadSubmission::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
