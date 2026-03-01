<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CacheKey
{
    /**
     * Build stable, role-aware cache key from params.
     *
     * @param  array<string,mixed>  $params
     */
    public static function make(string $prefix, array $params = [], ?User $user = null): string
    {
        ksort($params);
        $role = $user?->getRoleNames()?->first() ?? 'guest';
        $uid = $user?->id ?? 0;
        $namespace = explode(':', $prefix)[0] ?? $prefix;
        $version = (int) Cache::get('cache_version:' . $namespace, 1);

        return $prefix . ':' . sha1(json_encode([
            'u' => $uid,
            'r' => $role,
            'v' => $version,
            'p' => $params,
        ], JSON_UNESCAPED_SLASHES));
    }
}

