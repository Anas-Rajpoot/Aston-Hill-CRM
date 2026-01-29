<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserColumnPreference extends Model
{
    protected $fillable = ['user_id', 'module', 'visible_columns'];

    protected $casts = [
        'visible_columns' => 'array',
    ];
}
