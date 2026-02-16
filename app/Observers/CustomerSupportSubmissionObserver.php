<?php

namespace App\Observers;

use App\Models\CustomerSupportSubmission;
use App\Models\CustomerSupportSubmissionAudit;
use Illuminate\Support\Facades\Auth;

class CustomerSupportSubmissionObserver
{
    protected static array $oldValues = [];

    public function updating(CustomerSupportSubmission $submission): void
    {
        if (! $submission->exists) {
            return;
        }
        $dirty = $submission->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $submission->getOriginal();
        self::$oldValues[$submission->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function updated(CustomerSupportSubmission $submission): void
    {
        if (! $submission->exists) {
            return;
        }
        $changes = $submission->getChanges();
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$submission->id] ?? [];
        unset(self::$oldValues[$submission->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($submission->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $customerSupportSubmissionId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        CustomerSupportSubmissionAudit::create([
            'customer_support_submission_id' => $customerSupportSubmissionId,
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
