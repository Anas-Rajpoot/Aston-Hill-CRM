<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LibraryDocument extends Model
{
    public const CACHE_KEY = 'library_docs';
    public const FILE_TYPES = ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'image', 'other'];
    public const STATUSES   = ['active', 'inactive', 'archived'];
    public const VISIBILITIES = ['public', 'internal', 'restricted'];

    protected $fillable = [
        'document_code', 'name', 'description', 'category_id',
        'module_keys', 'tags', 'visibility', 'allowed_roles', 'allowed_departments',
        'file_type', 'mime_type', 'storage_disk', 'storage_path',
        'size_bytes', 'current_version', 'last_version_id', 'checksum_sha256',
        'status', 'uploaded_by', 'updated_by', 'archived_at',
    ];

    protected $casts = [
        'module_keys'         => 'array',
        'tags'                => 'array',
        'allowed_roles'       => 'array',
        'allowed_departments' => 'array',
        'size_bytes'          => 'integer',
        'current_version'     => 'integer',
        'archived_at'         => 'datetime',
    ];

    /* ── Relationships ── */
    public function category()  { return $this->belongsTo(LibraryCategory::class, 'category_id'); }
    public function uploader()  { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function updater()   { return $this->belongsTo(User::class, 'updated_by'); }
    public function versions()  { return $this->hasMany(LibraryDocumentVersion::class, 'document_id'); }

    /* ── Helpers ── */
    public function getIsPreviewableAttribute(): bool
    {
        return in_array($this->file_type, ['pdf', 'image']);
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public static function nextCode(): string
    {
        $last = self::orderByDesc('id')->value('document_code');
        $num  = $last ? ((int) substr($last, -4)) + 1 : 1;
        return 'LIB-' . date('Y') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public static function clearCache(): void { Cache::forget(self::CACHE_KEY . '_stats'); }

    /* ── Scopes ── */
    public function scopeNotArchived($q) { $q->whereNull('archived_at'); }

    public function scopeFilter($q, array $f)
    {
        if ($v = ($f['q'] ?? null))           $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%"));
        if ($v = ($f['category_id'] ?? null)) $q->where('category_id', $v);
        if ($v = ($f['file_type'] ?? null))   $q->where('file_type', $v);
        if ($v = ($f['status'] ?? null))      $q->where('status', $v);
        if ($v = ($f['visibility'] ?? null))  $q->where('visibility', $v);
        if ($v = ($f['uploaded_by'] ?? null))  $q->where('uploaded_by', $v);
        if ($v = ($f['date_from'] ?? null))   $q->whereDate('created_at', '>=', $v);
        if ($v = ($f['date_to'] ?? null))     $q->whereDate('created_at', '<=', $v);
        return $q;
    }

    public static function inferFileType(string $mime): string
    {
        if (str_contains($mime, 'pdf'))                                  return 'pdf';
        if (str_contains($mime, 'word') || str_contains($mime, 'docx')) return 'docx';
        if (str_contains($mime, 'sheet') || str_contains($mime, 'xlsx') || str_contains($mime, 'excel')) return 'xlsx';
        if (str_contains($mime, 'presentation') || str_contains($mime, 'pptx')) return 'pptx';
        if (str_contains($mime, 'csv') || str_contains($mime, 'comma')) return 'csv';
        if (str_starts_with($mime, 'image/'))                            return 'image';
        return 'other';
    }
}
