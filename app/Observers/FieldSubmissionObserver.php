<?php

namespace App\Observers;

use App\Models\FieldSubmission;
use App\Models\FieldSubmissionAudit;
use Illuminate\Support\Facades\Auth;

class FieldSubmissionObserver
{
    protected static array $oldValues = [];

    public function updating(FieldSubmission $fieldSubmission): void
    {
        if (! $fieldSubmission->exists) {
            return;
        }
        $dirty = $fieldSubmission->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $fieldSubmission->getOriginal();
        self::$oldValues[$fieldSubmission->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function updated(FieldSubmission $fieldSubmission): void
    {
        if (! $fieldSubmission->exists) {
            return;
        }
        $changes = $fieldSubmission->getChanges();
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$fieldSubmission->id] ?? [];
        unset(self::$oldValues[$fieldSubmission->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($fieldSubmission->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $fieldSubmissionId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        FieldSubmissionAudit::create([
            'field_submission_id' => $fieldSubmissionId,
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
