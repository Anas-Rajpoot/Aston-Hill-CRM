<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\UserLoginLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use App\Notifications\SuspiciousLoginAlert;

class LogUserLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $user = $event->user;

        $ip = request()->ip();
        $ua = substr((string) Request::userAgent(), 0, 500);
        $sessionId = session()->getId();

        // GeoIP (safe fallback)
        $country = null;
        $countryCode = null;
        try {
            $geo = geoip()->getLocation($ip);
            $country = $geo->country ?? null;
            $countryCode = $geo->iso_code ?? null;
        } catch (\Throwable $e) {
            // ignore if geoip not available
        }

        // Suspicious detection (simple starter rules)
        $isSuspicious = false;
        $reason = null;

        // rule: new country compared to last 10 logins
        $lastCountries = UserLoginLog::where('user_id', $user->id)
            ->whereNotNull('country_code')
            ->latest('login_at')
            ->limit(10)
            ->pluck('country_code')
            ->unique()
            ->toArray();

        if ($countryCode && count($lastCountries) > 0 && !in_array($countryCode, $lastCountries)) {
            $isSuspicious = true;
            $reason = "New country detected: {$countryCode}";
        }

        // rule: too many logins in short time
        $recentCount = UserLoginLog::where('user_id', $user->id)
            ->where('login_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentCount >= 5) {
            $isSuspicious = true;
            $reason = $reason ? ($reason.' | Too many logins') : 'Too many logins in 5 minutes';
        }

        $log = UserLoginLog::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'login_at' => now(),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'country' => $country,
            'country_code' => $countryCode,
            'role' => $user->getRoleNames()->first(),
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $reason,
        ]);

        if ($isSuspicious) {
            User::role('superadmin')->each(function ($admin) use ($log) {
                $admin->notify(new SuspiciousLoginAlert($log));
            });
        }
    }
}
