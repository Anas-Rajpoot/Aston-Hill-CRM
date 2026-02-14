<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'user_id', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function announcement() { return $this->belongsTo(Announcement::class); }
    public function user()         { return $this->belongsTo(User::class); }
}
