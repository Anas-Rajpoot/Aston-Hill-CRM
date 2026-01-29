<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleColumnService
{
    public static function module(string $module): array
    {
        return config("modules.$module", []);
    }

    public static function columns(string $module): array
    {
        return self::module($module)['columns'] ?? [];
    }

    public static function defaultColumns(string $module): array
    {
        return self::module($module)['default_columns'] ?? [];
    }

    public static function model(string $module): string
    {
        return self::module($module)['model'];
    }

    public static function defaultSort(string $module): array
    {
        return self::module($module)['default_sort'] ?? [];
    }

    public static function defaultColumnsForUser(string $module, $user): array
    {
        $roles = $user->roles->pluck('name')->toArray();
        $defaults = self::module($module)['default_columns'] ?? [];

        foreach ($roles as $role) {
            if (isset($defaults[$role])) {
                return $defaults[$role];
            }
        }

        return reset($defaults) ?? [];
    }

}
