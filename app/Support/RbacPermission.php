<?php

namespace App\Support;

use App\Models\User;
use App\Services\EffectivePermissionService;

class RbacPermission
{
    /**
     * Check user permission using module/action with backward-compatible aliases.
     *
     * @param  string|array<int,string>  $modules
     * @param  string  $action create|read|update|delete|assign_permissions
     * @param  array<int,string>  $legacyNames
     */
    public static function can(User $user, string|array $modules, string $action, array $legacyNames = []): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $moduleList = is_array($modules) ? $modules : [$modules];
        $actionAliases = self::actionAliases($action);
        $candidates = [];

        foreach ($moduleList as $module) {
            foreach ($actionAliases as $a) {
                $candidates[] = "{$module}.{$a}";
            }
        }

        $candidates = array_values(array_unique(array_merge($candidates, $legacyNames)));

        return app(EffectivePermissionService::class)->userHasAny($user, $candidates);
    }

    /**
     * @return array<int,string>
     */
    private static function actionAliases(string $action): array
    {
        $aliases = config('permissions.action_aliases', []);
        $resolved = $aliases[$action] ?? [$action];

        if (! in_array($action, $resolved, true)) {
            $resolved[] = $action;
        }

        return array_values(array_unique($resolved));
    }
}

