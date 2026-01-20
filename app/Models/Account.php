<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'company_name',
        'account_number',
        'assigned_csr_id',
    ];

    public function assignedCsr()
    {
        return $this->belongsTo(User::class, 'assigned_csr_id');
    }
}
