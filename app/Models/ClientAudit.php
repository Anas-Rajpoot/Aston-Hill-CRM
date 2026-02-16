<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAudit extends Model
{
    protected $table = 'client_audits';

    protected $fillable = [
        'client_id',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
