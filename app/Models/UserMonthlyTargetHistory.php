<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMonthlyTargetHistory extends Model
{
    protected $table = 'user_monthly_target_history';

    protected $fillable = [
        'user_id',
        'month',
        'target_amount',
        'set_by',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setByUser()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
