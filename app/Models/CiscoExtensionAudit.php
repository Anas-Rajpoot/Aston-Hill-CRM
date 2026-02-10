<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CiscoExtensionAudit extends Model
{
    protected $fillable = [
        'cisco_extension_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function ciscoExtension(): BelongsTo
    {
        return $this->belongsTo(CiscoExtension::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
