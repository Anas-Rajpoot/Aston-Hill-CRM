<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAlert extends Model
{
    protected $table = 'client_alerts';

    protected $fillable = [
        'client_id',
        'alert_type',
        'company_name',
        'account_number',
        'expiry_date',
        'days_remaining',
        'manager_id',
        'status',
        'status_date',
        'created_date',
        'resolved',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'status_date' => 'datetime',
        'created_date' => 'date',
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
