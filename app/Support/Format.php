<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Carbon\Carbon;

class Format
{
    public static function tz(): string
    {
        return auth()->user()?->timezone ?: config('app.timezone', 'UTC');
    }

    public static function d(?CarbonInterface $dt): string
    {
        return $dt ? $dt->format('d-M-Y') : '—';
    }

    public static function dt($date, ?string $tz = null): string
    {
        if (!$date) return '—';

        return Carbon::parse($date)->timezone($tz)->format('d-M-Y h:i A');

        // $timezone = $tz ?: self::tz();

        // return $date instanceof CarbonInterface
        //     ? $date->copy()->timezone($timezone)->format('d-M-Y h:i A')
        //     : \Carbon\Carbon::parse($date)->timezone($timezone)->format('d-M-Y h:i A');
    }

    public static function detectTzFromIp(?string $ip): ?string
    {
        if (!$ip) return null;

        try {
            $geo = geoip()->getLocation($ip);
            return $geo->timezone ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function tzForLog($log): string
    {
        $tz = $log->user?->timezone;

        if (!$tz) $tz = self::detectTzFromIp($log->ip_address);

        return $tz ?: 'UTC';
    }
}
