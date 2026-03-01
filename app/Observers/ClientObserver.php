<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ClientAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

        $this->flushClientCaches();
    }

    public function created(Client $client): void
    {
        $this->flushClientCaches();
    }

    public function deleted(Client $client): void
    {
        $this->flushClientCaches();
    }

    private function flushClientCaches(): void
    {
        $store = Cache::getStore();
        if (method_exists($store, 'tags')) {
            Cache::tags(['clients', 'filters'])->flush();
            return;
        }

        Cache::forget('clients:last-modified');
        Cache::forget('clients:filters:last-modified');
        if (! Cache::has('cache_version:clients')) {
            Cache::forever('cache_version:clients', 1);
        } else {
            Cache::increment('cache_version:clients');
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
            'client_id'   => $clientId,
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
