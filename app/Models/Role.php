<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function parentRoles()
    {
        return $this->belongsToMany(
            self::class,
            'role_inheritance',
            'child_role_id',
            'parent_role_id'
        )->withTimestamps();
    }

    public function childRoles()
    {
        return $this->belongsToMany(
            self::class,
            'role_inheritance',
            'parent_role_id',
            'child_role_id'
        )->withTimestamps();
    }
}
