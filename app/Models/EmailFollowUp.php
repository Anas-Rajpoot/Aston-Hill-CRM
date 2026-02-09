<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmailFollowUp extends Model
{
    public const STATUSES = ['pending', 'followed_up'];

    protected $fillable = [
        'created_by',
        'email_date',
        'subject',
        'category',
        'request_from',
        'sent_to',
        'comment',
        'status',
    ];

    protected $casts = [
        'email_date' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return $query;
        }
        return $query->where('created_by', $user?->id);
    }

    public function scopeFilter(Builder $q, array $f): Builder
    {
        return $q
            ->when(!empty($f['created_by']), fn($qq) => $qq->where('created_by', $f['created_by']))
            ->when(!empty($f['category']), fn($qq) => $qq->where('category', $f['category']))
            ->when(!empty($f['subject']), fn($qq) => $qq->where('subject', 'like', '%'.$f['subject'].'%'))
            ->when(!empty($f['request_from']), fn($qq) => $qq->where('request_from', 'like', '%'.$f['request_from'].'%'))
            ->when(!empty($f['sent_to']), fn($qq) => $qq->where('sent_to', 'like', '%'.$f['sent_to'].'%'))
            ->when(!empty($f['from']), fn($qq) => $qq->whereDate('email_date', '>=', $f['from']))
            ->when(!empty($f['to']), fn($qq) => $qq->whereDate('email_date', '<=', $f['to']));
    }
}
