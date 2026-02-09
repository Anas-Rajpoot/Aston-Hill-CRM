<?php

namespace App\Observers;

use App\Models\LeadSubmission;
use App\Models\LeadSubmissionAudit;
use Illuminate\Support\Facades\Auth;

class LeadSubmissionObserver
{
    /** Old attribute values for the model currently being updated (keyed by model id). */
    protected static array $oldValues = [];

    /**
     * Before update: capture current (old) values for all dirty attributes.
     */
    public function updating(LeadSubmission $lead): void
    {
        if (! $lead->exists) {
            return;
        }
        $dirty = $lead->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $lead->getOriginal();
        self::$oldValues[$lead->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    /**
     * After update: log each changed field to lead_submission_audits.
     */
    public function updated(LeadSubmission $lead): void
    {
        if (! $lead->exists) {
            return;
        }
        $changes = $lead->getChanges();
        // Remove timestamp columns from logging if you only want business fields
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$lead->id] ?? [];
        unset(self::$oldValues[$lead->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($lead->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $leadSubmissionId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        LeadSubmissionAudit::create([
            'lead_submission_id' => $leadSubmissionId,
            'field_name' => $fieldName,
            'old_value' => $this->serializeValue($oldValue),
            'new_value' => $this->serializeValue($newValue),
            'changed_at' => $changedAt,
            'changed_by' => $changedBy,
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
