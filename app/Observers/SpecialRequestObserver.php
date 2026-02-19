<?php

namespace App\Observers;

use App\Models\SpecialRequest;
use App\Models\SpecialRequestAudit;
use Illuminate\Support\Facades\Auth;

class SpecialRequestObserver
{
    protected static array $oldValues = [];

    public function updating(SpecialRequest $specialRequest): void
    {
        if (! $specialRequest->exists) {
            return;
        }
        $dirty = $specialRequest->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $specialRequest->getOriginal();
        self::$oldValues[$specialRequest->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function updated(SpecialRequest $specialRequest): void
    {
        if (! $specialRequest->exists) {
            return;
        }
        $changes = $specialRequest->getChanges();
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$specialRequest->id] ?? [];
        unset(self::$oldValues[$specialRequest->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            SpecialRequestAudit::create([
                'special_request_id' => $specialRequest->id,
                'field_name' => $field,
                'old_value' => $this->serializeValue($oldValue),
                'new_value' => $this->serializeValue($newValue),
                'changed_at' => $changedAt,
                'changed_by' => $changedBy,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);
        }
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
}
