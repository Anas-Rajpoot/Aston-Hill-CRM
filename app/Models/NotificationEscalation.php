<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEscalation extends Model
{
    protected $fillable = ['level', 'to_emails', 'enabled', 'updated_by'];

    protected $casts = [
        'to_emails' => 'array',
        'enabled'   => 'boolean',
        'level'     => 'integer',
    ];
}
