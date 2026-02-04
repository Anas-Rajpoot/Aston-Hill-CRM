<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldSubmissionDocument extends Model
{
    protected $fillable = [
        'field_submission_id',
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

    public function fieldSubmission(): BelongsTo
    {
        return $this->belongsTo(FieldSubmission::class);
    }
}
