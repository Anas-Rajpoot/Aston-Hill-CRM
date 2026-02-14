<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'trigger_key', 'channel', 'module',
        'sent_to', 'status', 'error', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
