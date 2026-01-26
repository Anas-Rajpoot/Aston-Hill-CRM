<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Announcement extends Model
{
    use Notifiable;
    protected $fillable = [
        'created_by',
        'title',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'is_pinned',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getHasAttachmentAttribute(): bool
    {
        return !empty($this->attachment_path);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? asset('storage/'.$this->attachment_path) : null;
    }

    public function scopeFilter($query, array $f)
    {
        return $query
            ->when(isset($f['active']) && $f['active'] !== '', fn($qq) => $qq->where('is_active', (int)$f['active']))
            ->when(isset($f['pinned']) && $f['pinned'] !== '', fn($qq) => $qq->where('is_pinned', (int)$f['pinned']))
            ->when(!empty($f['has_attachment']), fn($qq) => $qq->whereNotNull('attachment_path'))
            ->when(!empty($f['q']), function ($qq) use ($f) {
                $term = $f['q'];
                $qq->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('body', 'like', "%{$term}%");
                });
            })
            ->when(!empty($f['from']), fn($qq) => $qq->whereDate('created_at', '>=', $f['from']))
            ->when(!empty($f['to']), fn($qq) => $qq->whereDate('created_at', '<=', $f['to']));
    }

}
