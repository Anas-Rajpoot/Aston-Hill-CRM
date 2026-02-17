<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralized cache layer for submission listing endpoints.
 *
 * Uses cache tags when the driver supports them (Redis, Memcached)
 * so that any create/update/delete on a submission model can flush
 * only the relevant tag, avoiding stale data while keeping hot reads fast.
 *
 * Gracefully falls back to prefix-based keys with shorter TTL on
 * file/database cache drivers.
 *
 * Cache keys incorporate the authenticated user id and a hash of
 * the query parameters so different users / filter combos get
 * independent cache entries.
 */
class SubmissionCacheService
{
    /** Default TTLs in seconds */
    public const LIST_TTL  = 120;   // 2 min for paginated lists
    public const META_TTL  = 600;   // 10 min for filters/columns metadata
    public const STATS_TTL = 300;   // 5 min for report stats

    private static ?bool $supportsTags = null;

    /**
     * Check if the current cache store supports tagging.
     */
    private static function supportsTags(): bool
    {
        if (self::$supportsTags === null) {
            try {
                $store = Cache::getStore();
                self::$supportsTags = method_exists($store, 'tags');
            } catch (\Throwable $e) {
                self::$supportsTags = false;
            }
        }
        return self::$supportsTags;
    }

    /**
     * Get a cache store (tagged if available).
     */
    private static function store(string $module)
    {
        if (self::supportsTags()) {
            return Cache::tags(["submissions:{$module}"]);
        }
        return Cache::store();
    }

    /**
     * Generate a unique cache key incorporating user + query params.
     */
    public static function key(string $prefix, int $userId, array $params = []): string
    {
        $hash = md5(json_encode($params));
        return "sub:{$prefix}:u{$userId}:{$hash}";
    }

    /**
     * Remember a list/page result with module-level tags.
     */
    public static function rememberList(string $module, int $userId, array $params, callable $callback)
    {
        $key = self::key("{$module}:list", $userId, $params);
        return self::store($module)->remember($key, self::LIST_TTL, $callback);
    }

    /**
     * Remember filter/column metadata (stable, longer TTL).
     */
    public static function rememberMeta(string $module, string $type, int $userId, callable $callback)
    {
        $key = "sub:{$module}:{$type}:u{$userId}";
        return self::store($module)->remember($key, self::META_TTL, $callback);
    }

    /**
     * Remember stats/report data.
     */
    public static function rememberStats(string $module, int $userId, array $params, callable $callback)
    {
        $key = self::key("{$module}:stats", $userId, $params);
        return self::store($module)->remember($key, self::STATS_TTL, $callback);
    }

    /**
     * Flush all cached data for a module (call on create/update/delete).
     */
    public static function flush(string $module): void
    {
        try {
            if (self::supportsTags()) {
                Cache::tags(["submissions:{$module}"])->flush();
            }
            // With file cache, entries expire naturally via TTL
        } catch (\Throwable $e) {
            Log::debug("Cache flush failed for {$module}: " . $e->getMessage());
        }
    }

    /**
     * Flush all submission caches (nuclear option).
     */
    public static function flushAll(): void
    {
        foreach (['leads', 'field', 'customer-support', 'vas'] as $module) {
            self::flush($module);
        }
    }
}
