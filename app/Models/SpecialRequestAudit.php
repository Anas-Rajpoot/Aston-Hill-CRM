<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialRequestAudit extends Model
{
    protected $fillable = [
        'special_request_id',
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

    public function specialRequest()
    {
        return $this->belongsTo(SpecialRequest::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
