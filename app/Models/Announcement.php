<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Announcement extends Model
{
    public const CACHE_KEY   = 'announcements';
    public const CACHE_TTL   = 600;
    public const TYPES       = ['text', 'image', 'link', 'banner'];
    public const PRIORITIES  = ['low', 'normal', 'high', 'critical'];

    protected $fillable = [
        'title', 'type', 'body', 'link_url', 'link_label',
        'priority', 'all_users', 'audiences', 'channels',
        'is_pinned', 'require_ack', 'ack_due_at',
        'published_at', 'expire_at', 'archived_at',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'all_users'    => 'boolean',
        'audiences'    => 'array',
        'channels'     => 'array',
        'is_pinned'    => 'boolean',
        'is_active'    => 'boolean',
        'require_ack'  => 'boolean',
        'ack_due_at'   => 'datetime',
        'published_at' => 'datetime',
        'expire_at'    => 'datetime',
        'archived_at'  => 'datetime',
    ];

    /* ─── Computed status accessor ─────────────────────── */

    public function getStatusAttribute(): string
    {
        if ($this->archived_at)                                      return 'disabled';
        $now = now();
        if ($this->published_at && $this->published_at->isFuture())  return 'scheduled';
        if ($this->expire_at && $this->expire_at->isPast())          return 'expired';
        if ($this->published_at && $this->published_at->lte($now))   return 'active';
        return 'draft';
    }

    /* ─── Scopes ───────────────────────────────────────── */

    public function scopeActive($q)
    {
        $now = now();
        $q->whereNull('archived_at')
          ->where('published_at', '<=', $now)
          ->where(fn ($w) => $w->whereNull('expire_at')->orWhere('expire_at', '>', $now));
    }

    public function scopeScheduled($q)
    {
        $q->whereNull('archived_at')->where('published_at', '>', now());
    }

    public function scopeExpired($q)
    {
        $q->whereNull('archived_at')
          ->whereNotNull('expire_at')
          ->where('expire_at', '<=', now());
    }

    public function scopeArchived($q)  { $q->whereNotNull('archived_at'); }
    public function scopeNotArchived($q) { $q->whereNull('archived_at'); }

    /* ─── Relationships ────────────────────────────────── */

    public function creator()          { return $this->belongsTo(User::class, 'created_by'); }
    public function updater()          { return $this->belongsTo(User::class, 'updated_by'); }
    public function acknowledgements() { return $this->hasMany(AnnouncementAcknowledgement::class); }

    /* ─── Cache helpers ────────────────────────────────── */

    public static function clearCache(): void { Cache::forget(self::CACHE_KEY . '_counters'); }

    public static function counters(): array
    {
        return Cache::remember(self::CACHE_KEY . '_counters', self::CACHE_TTL, function () {
            return [
                'total'     => self::count(),
                'active'    => self::active()->count(),
                'scheduled' => self::scheduled()->count(),
                'expired'   => self::expired()->count(),
                'disabled'  => self::archived()->count(),
            ];
        });
    }
}
