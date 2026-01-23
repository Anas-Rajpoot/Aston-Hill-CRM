<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PersonalNote extends Model
{
    protected $fillable = [
        'user_id','title','body','status','priority','due_date','completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFilter(Builder $q, array $f): Builder
    {
        if (!empty($f['status']))   $q->where('status', $f['status']);
        if (!empty($f['priority'])) $q->where('priority', $f['priority']);

        if (!empty($f['from'])) $q->whereDate('created_at', '>=', $f['from']);
        if (!empty($f['to']))   $q->whereDate('created_at', '<=', $f['to']);

        if (!empty($f['q'])) {
            $term = trim($f['q']);
            $q->where(function ($w) use ($term) {
                $w->where('title', 'like', "%{$term}%")
                  ->orWhere('body', 'like', "%{$term}%");
            });
        }

        return $q;
    }
}
