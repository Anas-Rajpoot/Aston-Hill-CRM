<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSubmissionDocument extends Model
{
    protected $fillable = [
        'lead_submission_id',
        'service_type_id',
        'doc_key',
        'file_path',
        'file_name',
        'label',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function leadSubmission(): BelongsTo
    {
        return $this->belongsTo(LeadSubmission::class);
    }
}
