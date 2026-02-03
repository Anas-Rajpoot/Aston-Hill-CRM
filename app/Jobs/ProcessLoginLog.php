<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserLoginLog;
use App\Notifications\SuspiciousLoginAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Queued job to write login log and optionally send suspicious-login alerts.
 * Keeps login HTTP response fast; run queue worker in production.
 */
class ProcessLoginLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public string $ip,
        public string $userAgent
    ) {}

    public function handle(): void
    {
        $country = null;
        $countryCode = null;
        try {
            $geo = geoip()->getLocation($this->ip);
            $country = $geo->country ?? null;
            $countryCode = $geo->iso_code ?? null;
        } catch (\Throwable $e) {
            //
        }

        $isSuspicious = false;
        $reason = null;

        $lastCountries = UserLoginLog::where('user_id', $this->userId)
            ->whereNotNull('country_code')
            ->latest('login_at')
            ->limit(10)
            ->pluck('country_code')
            ->unique()
            ->toArray();

        if ($countryCode && count($lastCountries) > 0 && ! in_array($countryCode, $lastCountries)) {
            $isSuspicious = true;
            $reason = "New country detected: {$countryCode}";
        }

        $recentCount = UserLoginLog::where('user_id', $this->userId)
            ->where('login_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentCount >= 5) {
            $isSuspicious = true;
            $reason = $reason ? ($reason.' | Too many logins') : 'Too many logins in 5 minutes';
        }

        $modelType = (new User)->getMorphClass();
        $roleName = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->userId)
            ->where('model_has_roles.model_type', $modelType)
            ->value('roles.name');

        $log = UserLoginLog::create([
            'user_id' => $this->userId,
            'session_id' => $this->sessionId,
            'login_at' => now(),
            'ip_address' => $this->ip,
            'user_agent' => substr($this->userAgent, 0, 500),
            'country' => $country,
            'country_code' => $countryCode,
            'role' => $roleName,
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $reason,
        ]);

        if ($isSuspicious) {
            User::role('superadmin')->each(fn (User $admin) => $admin->notify(new SuspiciousLoginAlert($log)));
        }
    }
}
