<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    protected static array $oldValues = [];

    /** Fields we never store in audit (sensitive or internal). */
    protected const SKIP_FIELDS = [
        'password', 'remember_token', 'two_factor_secret',
        'updated_at', 'two_factor_confirmed_at',
    ];

    public function updating(User $user): void
    {
        if (! $user->exists) {
            return;
        }
        $dirty = $user->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $user->getOriginal();
        self::$oldValues[$user->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function deleted(User $user): void
    {
        $this->invalidateUserCache($user->id);

        UserAudit::create([
            'user_id'    => $user->id,
            'field_name' => '_deleted',
            'old_value'  => $this->serializeValue($user->email),
            'new_value'  => null,
            'changed_at' => now(),
            'changed_by' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    public function updated(User $user): void
    {
        if (! $user->exists) {
            return;
        }
        $this->invalidateUserCache($user->id);
        $changes = $user->getChanges();
        $oldSnapshot = self::$oldValues[$user->id] ?? [];
        unset(self::$oldValues[$user->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, self::SKIP_FIELDS, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($user->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $userId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        UserAudit::create([
            'user_id'    => $userId,
            'field_name' => $fieldName,
            'old_value'  => $this->serializeValue($oldValue),
            'new_value'  => $this->serializeValue($newValue),
            'changed_at' => $changedAt,
            'changed_by' => $changedBy,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    protected function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toIso8601String();
        }
        return (string) $value;
    }

    /** Invalidate cached user data so next request gets fresh data (Redis tagged cache). */
    protected function invalidateUserCache(int $userId): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags(['user.' . $userId])->flush();
                return;
            }
        } catch (\Throwable $e) {
            // Driver does not support tags – fall through to manual forget.
        }

        Cache::forget('user.prime.' . $userId);
        Cache::forget('user.extras.' . $userId);
    }
}
