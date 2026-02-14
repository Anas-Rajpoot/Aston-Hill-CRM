<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormDraft extends Model
{
    protected $fillable = ['user_id', 'module', 'record_ref', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public const EXPIRY_DAYS = 30;

    public function user() { return $this->belongsTo(User::class); }

    /**
     * Find a draft for user + module + record.
     */
    public static function findDraft(int $userId, string $module, string $recordRef): ?self
    {
        return self::where('user_id', $userId)
            ->where('module', $module)
            ->where('record_ref', $recordRef)
            ->first();
    }

    /**
     * Upsert a draft.
     */
    public static function saveDraft(int $userId, string $module, string $recordRef, array $data): self
    {
        return self::updateOrCreate(
            ['user_id' => $userId, 'module' => $module, 'record_ref' => $recordRef],
            ['data' => $data]
        );
    }

    /**
     * Remove a draft (e.g., after successful submit).
     */
    public static function clearDraft(int $userId, string $module, string $recordRef): bool
    {
        return (bool) self::where('user_id', $userId)
            ->where('module', $module)
            ->where('record_ref', $recordRef)
            ->delete();
    }

    /**
     * Purge drafts older than EXPIRY_DAYS.
     */
    public static function purgeExpired(): int
    {
        return self::where('updated_at', '<', now()->subDays(self::EXPIRY_DAYS))->delete();
    }
}
