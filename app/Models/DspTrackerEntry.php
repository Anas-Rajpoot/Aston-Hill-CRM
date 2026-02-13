<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DspTrackerEntry extends Model
{
    protected $table = 'dsp_tracker_entries';

    protected $fillable = [
        'import_batch_id',
        'activity_number',
        'company_name',
        'account_number',
        'request_type',
        'appointment_date',
        'appointment_time',
        'product',
        'so_number',
        'request_status',
        'rejection_reason',
        'verifier_name',
        'verifier_number',
        'dsp_om_id',
        'uploaded_by',
        'uploaded_at',
        'user_id',
    ];

    protected $casts = [
        //
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
