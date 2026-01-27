<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSubmissionColumnPreference extends Model
{
    protected $fillable = [
        'user_id',
        'visible_columns',
    ];

    protected $casts = [
        'visible_columns' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
