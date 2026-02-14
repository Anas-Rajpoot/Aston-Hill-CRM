<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'trigger_key', 'name', 'subject', 'body',
        'available_variables', 'updated_by',
    ];

    protected $casts = [
        'available_variables' => 'array',
    ];
}
