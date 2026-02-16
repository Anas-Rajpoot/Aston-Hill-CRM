<?php

namespace App\Observers;

use App\Models\VasRequestAudit;
use App\Models\VasRequestSubmission;
use Illuminate\Support\Facades\Auth;

class VasRequestSubmissionObserver
{
    protected static array $oldValues = [];

    public function updating(VasRequestSubmission $vas): void
    {
        if (! $vas->exists) {
            return;
        }
        $dirty = $vas->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $vas->getOriginal();
        self::$oldValues[$vas->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function updated(VasRequestSubmission $vas): void
    {
        if (! $vas->exists) {
            return;
        }
        $changes = $vas->getChanges();
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$vas->id] ?? [];
        unset(self::$oldValues[$vas->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($vas->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $vasRequestSubmissionId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        VasRequestAudit::create([
            'vas_request_submission_id' => $vasRequestSubmissionId,
            'field_name'  => $fieldName,
            'old_value'   => $this->serializeValue($oldValue),
            'new_value'   => $this->serializeValue($newValue),
            'changed_at'  => $changedAt,
            'changed_by'  => $changedBy,
            'ip_address'  => request()->ip(),
            'user_agent'  => substr((string) request()->userAgent(), 0, 500),
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
}
