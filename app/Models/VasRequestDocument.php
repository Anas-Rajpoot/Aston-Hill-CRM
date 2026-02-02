<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VasRequestDocument extends Model
{
    protected $table = 'vas_request_documents';

    protected $fillable = [
        'vas_request_submission_id',
        'doc_key',
        'file_path',
        'file_name',
        'label',
    ];

    public function vasRequestSubmission()
    {
        return $this->belongsTo(VasRequestSubmission::class);
    }
}
