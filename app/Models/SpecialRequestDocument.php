<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialRequestDocument extends Model
{
    protected $fillable = [
        'special_request_id',
        'doc_key',
        'label',
        'file_path',
        'file_name',
        'mime',
        'size',
    ];

    public function specialRequest()
    {
        return $this->belongsTo(SpecialRequest::class);
    }
}
