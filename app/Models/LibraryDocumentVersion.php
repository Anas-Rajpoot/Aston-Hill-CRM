<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryDocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id', 'version', 'change_note',
        'storage_disk', 'storage_path', 'mime_type',
        'size_bytes', 'checksum_sha256', 'uploaded_by', 'created_at',
    ];

    protected $casts = [
        'size_bytes'  => 'integer',
        'version'     => 'integer',
        'created_at'  => 'datetime',
    ];

    public function document() { return $this->belongsTo(LibraryDocument::class, 'document_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function getSizeHumanAttribute(): string
    {
        $b = $this->size_bytes;
        if ($b < 1024) return $b . ' B';
        if ($b < 1048576) return round($b / 1024, 1) . ' KB';
        return round($b / 1048576, 1) . ' MB';
    }
}
