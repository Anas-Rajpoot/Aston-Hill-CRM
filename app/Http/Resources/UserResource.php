<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean API resource for User. Supports sparse fieldsets via ?fields[user]=id,name,email.
 * Only requested fields (or default set) are included to reduce payload size.
 */
class UserResource extends JsonResource
{
    /** Default fields when no sparse fieldset is requested (edit page prime payload). */
    public const DEFAULT_PRIME_FIELDS = [
        'id', 'name', 'email', 'phone', 'country', 'status',
        'manager_id', 'team_leader_id', 'department', 'extension',
        'joining_date', 'terminate_date', 'employee_number', 'cnic_number', 'additional_notes',
        'created_at', 'updated_at', 'roles',
    ];

    public function toArray(Request $request): array
    {
        $requested = $this->parseFieldsParam($request, 'user');
        $fields = !empty($requested) ? $requested : self::DEFAULT_PRIME_FIELDS;
        $fields = array_intersect($fields, array_merge(self::DEFAULT_PRIME_FIELDS, ['last_login_at', 'roles']));

        $data = [];
        foreach ($fields as $key) {
            $data[$key] = $this->valueFor($key);
        }

        // Include roles as minimal array when requested (for edit form)
        if (in_array('roles', $fields, true) && $this->relationLoaded('roles')) {
            $data['roles'] = $this->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);
        }

        return $data;
    }

    private function valueFor(string $key): mixed
    {
        return match ($key) {
            'joining_date' => $this->joining_date?->format('Y-m-d'),
            'terminate_date' => $this->terminate_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            default => $this->$key ?? null,
        };
    }

    /** Parse ?fields[resource]=id,name,email into array. */
    private function parseFieldsParam(Request $request, string $resource): array
    {
        $fields = $request->query('fields');
        if (!is_array($fields) || empty($fields[$resource])) {
            return [];
        }
        $str = $fields[$resource];
        if (!is_string($str)) {
            return [];
        }
        return array_map('trim', explode(',', $str));
    }
}
