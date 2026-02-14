<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks individual escalation notifications sent per submission per level.
 * Used by CheckEscalationJob to avoid duplicate sends.
 */
class EscalationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'module_key',
        'record_id',
        'escalation_level',
        'sent_to',
        'recipient_type',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Check if a specific escalation level has already been sent for a record.
     */
    public static function alreadySent(string $moduleKey, int $recordId, int $level): bool
    {
        return self::where('module_key', $moduleKey)
            ->where('record_id', $recordId)
            ->where('escalation_level', $level)
            ->where('status', 'sent')
            ->exists();
    }
}
