<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleInheritanceService
{
    private const CACHE_PREFIX = 'rbac.role_inheritance.parents.';
    private const CACHE_TTL = 3600;

    /**
     * @return array<int,int> Role IDs including the source role.
     */
    public function resolveAncestorRoleIds(int $roleId): array
    {
        return Cache::remember(self::CACHE_PREFIX . $roleId, self::CACHE_TTL, function () use ($roleId) {
            if (! $this->hasRoleInheritanceTable()) {
                return [$roleId];
            }

            $visited = [];
            $stack = [$roleId];

            while (! empty($stack)) {
                $current = array_pop($stack);
                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;

                $parents = DB::table('role_inheritance')
                    ->where('child_role_id', $current)
                    ->pluck('parent_role_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                foreach ($parents as $parentId) {
                    if (! isset($visited[$parentId])) {
                        $stack[] = $parentId;
                    }
                }
            }

            return array_map('intval', array_keys($visited));
        });
    }

    public function addEdge(Role $parent, Role $child): void
    {
        if (! $this->hasRoleInheritanceTable()) {
            return;
        }

        if ($parent->id === $child->id) {
            abort(422, 'A role cannot inherit from itself.');
        }

        $ancestorIds = $this->resolveAncestorRoleIds((int) $parent->id);
        if (in_array((int) $child->id, $ancestorIds, true)) {
            abort(422, 'Role inheritance cycle detected.');
        }

        DB::table('role_inheritance')->updateOrInsert([
            'parent_role_id' => (int) $parent->id,
            'child_role_id' => (int) $child->id,
        ], [
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $this->flushAll();
    }

    public function removeEdge(Role $parent, Role $child): void
    {
        if (! $this->hasRoleInheritanceTable()) {
            return;
        }

        DB::table('role_inheritance')
            ->where('parent_role_id', (int) $parent->id)
            ->where('child_role_id', (int) $child->id)
            ->delete();

        $this->flushAll();
    }

    public function flushAll(): void
    {
        $roleIds = Role::query()->pluck('id')->all();
        foreach ($roleIds as $id) {
            Cache::forget(self::CACHE_PREFIX . (int) $id);
        }
    }

    private function hasRoleInheritanceTable(): bool
    {
        try {
            return Schema::hasTable('role_inheritance');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
