<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSubmissionAudit extends Model
{
    protected $fillable = [
        'field_submission_id',
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

    public function fieldSubmission()
    {
        return $this->belongsTo(FieldSubmission::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
