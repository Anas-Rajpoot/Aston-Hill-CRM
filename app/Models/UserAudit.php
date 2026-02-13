<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAudit extends Model
{
    protected $table = 'user_audits';

    protected $fillable = [
        'user_id',
        'field_name',
        'old_value',
        'new_value',
        'changed_at',
        'changed_by',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
