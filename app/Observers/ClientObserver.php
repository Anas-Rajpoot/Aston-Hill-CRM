<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ClientAudit;
use Illuminate\Support\Facades\Auth;

class ClientObserver
{
    protected static array $oldValues = [];

    public function updating(Client $client): void
    {
        if (! $client->exists) {
            return;
        }
        $dirty = $client->getDirty();
        if (empty($dirty)) {
            return;
        }
        $original = $client->getOriginal();
        self::$oldValues[$client->id] = array_intersect_key($original, array_flip(array_keys($dirty)));
    }

    public function updated(Client $client): void
    {
        if (! $client->exists) {
            return;
        }
        $changes = $client->getChanges();
        $skipKeys = ['updated_at'];
        $oldSnapshot = self::$oldValues[$client->id] ?? [];
        unset(self::$oldValues[$client->id]);

        $changedBy = Auth::id();
        $changedAt = now();

        foreach ($changes as $field => $newValue) {
            if (in_array($field, $skipKeys, true)) {
                continue;
            }
            $oldValue = array_key_exists($field, $oldSnapshot) ? $oldSnapshot[$field] : null;
            $this->auditRow($client->id, $field, $oldValue, $newValue, $changedAt, $changedBy);
        }
    }

    protected function auditRow(
        int $clientId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        $changedAt,
        ?int $changedBy
    ): void {
        ClientAudit::create([
            'client_id' => $clientId,
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
