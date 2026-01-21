<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'login_at',
        'logout_at',
        'ip_address',
        'user_agent',
        'country',
        'country_code',
        'city',
        'role',
        'is_suspicious',
        'suspicious_reason',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'is_suspicious' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // seconds active for this row (if still online => now)
    public function getActiveSecondsAttribute(): int
    {
        if (!$this->login_at) return null;
        $end = $this->logout_at ?? now();
        return max(0, $end->diffInSeconds($this->login_at));
    }
}
