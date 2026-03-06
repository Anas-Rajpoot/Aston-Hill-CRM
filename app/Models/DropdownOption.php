<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DropdownOption extends Model
{
    protected $fillable = [
        'group',
        'value',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /* ───── Scopes ───── */

    public function scopeForGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('value');
    }

    /* ───── Helpers ───── */

    /**
     * Get all active values for a dropdown group, ordered.
     */
    public static function valuesForGroup(string $group): array
    {
        return static::forGroup($group)
            ->active()
            ->ordered()
            ->pluck('value')
            ->toArray();
    }

    /**
     * Get all active options for a dropdown group as [{value, label}], ordered.
     */
    public static function optionsForGroup(string $group): array
    {
        return static::forGroup($group)
            ->active()
            ->ordered()
            ->get(['value', 'label'])
            ->map(fn ($o) => [
                'value' => $o->value,
                'label' => $o->label ?: $o->value,
            ])
            ->toArray();
    }

    /**
     * Get all unique group names.
     */
    public static function allGroups(): array
    {
        return static::distinct()->pluck('group')->sort()->values()->toArray();
    }
}
