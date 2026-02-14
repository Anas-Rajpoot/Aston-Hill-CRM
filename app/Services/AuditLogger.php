<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Lightweight audit-log writer.
 * Wraps in try/catch so it never breaks the calling request.
 */
class AuditLogger
{
    /**
     * Record an audit-log entry.
     */
    public static function record(array $attrs): ?AuditLog
    {
        try {
            return AuditLog::create(array_merge([
                'occurred_at' => now(),
                'user_id'     => auth()->id(),
                'user_name'   => auth()->user()?->name ?? 'System',
                'user_role'   => self::primaryRole(),
                'ip'          => request()->ip(),
                'user_agent'  => substr((string) request()->userAgent(), 0, 1000),
                'device'      => self::parseDevice(request()->userAgent()),
                'session_id'  => session()->getId(),
                'route'       => request()->route()?->getName(),
                'method'      => request()->method(),
                'result'      => 'success',
            ], $attrs));
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    /**
     * Compute diff between old and new arrays (changed fields only).
     */
    public static function diff(?array $old, ?array $new): array
    {
        if (! $old || ! $new) return ['old' => $old, 'new' => $new];

        $changed_old = [];
        $changed_new = [];

        foreach ($new as $k => $v) {
            if (json_encode($old[$k] ?? null) !== json_encode($v)) {
                $changed_old[$k] = $old[$k] ?? null;
                $changed_new[$k] = $v;
            }
        }

        return ['old' => $changed_old ?: null, 'new' => $changed_new ?: null];
    }

    private static function primaryRole(): ?string
    {
        $user = auth()->user();
        if (! $user || ! method_exists($user, 'getRoleNames')) return null;
        return $user->getRoleNames()->first();
    }

    public static function parseDevice(?string $ua): string
    {
        if (! $ua) return 'Unknown';

        $browser = 'Unknown';
        $os      = 'Unknown';

        if (str_contains($ua, 'Edg/'))           $browser = 'Edge';
        elseif (str_contains($ua, 'OPR/'))        $browser = 'Opera';
        elseif (str_contains($ua, 'Chrome/'))      $browser = 'Chrome';
        elseif (str_contains($ua, 'Firefox/'))     $browser = 'Firefox';
        elseif (str_contains($ua, 'Safari/'))      $browser = 'Safari';
        elseif (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident/')) $browser = 'IE';

        if (str_contains($ua, 'Windows'))          $os = 'Windows';
        elseif (str_contains($ua, 'Macintosh'))    $os = 'macOS';
        elseif (str_contains($ua, 'Linux'))        $os = 'Linux';
        elseif (str_contains($ua, 'Android'))      $os = 'Android';
        elseif (str_contains($ua, 'iPhone'))       $os = 'iOS';

        return "{$browser} / {$os}";
    }
}
