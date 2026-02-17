<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'name',
        'description',
        'manager_id',
        'team_leader_id',
        'department',
        'status',
        'max_members',
    ];

    protected $casts = [
        'max_members' => 'integer',
    ];

    const STATUSES = ['active', 'inactive'];

    /* ───── Relationships ───── */

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members()
    {
        return $this->hasMany(User::class, 'team_id');
    }
}
