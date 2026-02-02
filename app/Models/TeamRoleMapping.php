<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class TeamRoleMapping extends Model
{
    protected $fillable = ['slot_key', 'role_id', 'sort_order'];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get role_id for a slot. Returns null if not mapped.
     */
    public static function roleIdFor(string $slotKey): ?int
    {
        $mapping = static::where('slot_key', $slotKey)->first();
        return $mapping?->role_id;
    }

    /**
     * Get all mappings keyed by slot_key [manager => role_id, ...].
     */
    public static function allMappings(): array
    {
        return static::with('role')->orderBy('sort_order')->get()
            ->keyBy('slot_key')
            ->map(fn ($m) => ['role_id' => $m->role_id, 'label' => $m->role?->name ?? $m->slot_key])
            ->toArray();
    }

    /**
     * Update mappings from super admin. Accepts [manager => role_id, team_leader => role_id, sales_agent => role_id].
     */
    public static function updateMappings(array $slots): void
    {
        $defaultSlots = ['manager', 'team_leader', 'sales_agent'];
        foreach ($defaultSlots as $i => $slotKey) {
            $roleId = $slots[$slotKey] ?? null;
            if ($roleId) {
                static::updateOrCreate(
                    ['slot_key' => $slotKey],
                    ['role_id' => $roleId, 'sort_order' => $i + 1]
                );
            } else {
                static::where('slot_key', $slotKey)->delete();
            }
        }
    }
}
