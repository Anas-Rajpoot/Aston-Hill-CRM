<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\LibraryDocument;
use App\Models\NotificationSetting;
use App\Models\SecuritySetting;
use App\Models\SlaRule;
use App\Models\SystemPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SettingsStatusController extends Controller
{
    /**
     * Return live system status values for the Settings dashboard.
     * Cached for 2 minutes to avoid hitting DB on every page load.
     */
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('settings_status', 120, function () {
            $security = SecuritySetting::current();
            $prefs    = SystemPreference::singleton();

            // Format session timeout as human-readable
            $minutes = (int) $security->auto_logout_after_minutes;
            if ($minutes >= 1440) {
                $sessionTimeout = ($minutes / 1440) . ' Day' . ($minutes >= 2880 ? 's' : '');
            } elseif ($minutes >= 60) {
                $hours = $minutes / 60;
                $sessionTimeout = $hours . ' Hour' . ($hours > 1 ? 's' : '');
            } else {
                $sessionTimeout = $minutes . ' Minutes';
            }

            // Count active SLA rules
            $activeSlaCount = SlaRule::where('is_active', true)->count();

            // Count enabled notification types
            $notifSetting = NotificationSetting::first();
            $enabledTypes = 0;
            if ($notifSetting) {
                if ($notifSetting->enable_email)      $enabledTypes++;
                if ($notifSetting->enable_web)         $enabledTypes++;
                if ($notifSetting->enable_sms)         $enabledTypes++;
                if ($notifSetting->enable_sla_alerts)  $enabledTypes++;
            }

            // Count active announcements
            $announcementsCount = Announcement::where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expire_at')->orWhere('expire_at', '>', now());
                })
                ->count();

            // Count library documents
            $libraryDocsCount = LibraryDocument::where('status', 'active')->count();

            // Count audit log entries
            $auditLogsCount = AuditLog::count();

            // Language / locale from app config
            $locale = config('app.locale', 'en');
            $languages = ['en' => 'English', 'ar' => 'Arabic', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German'];
            $language = $languages[$locale] ?? ucfirst($locale);

            // Timezone
            $timezone = $prefs->timezone ?? config('app.timezone', 'Asia/Dubai');

            return [
                'sessionTimeout'       => $sessionTimeout,
                'activeSlaRules'       => $activeSlaCount > 0 ? "{$activeSlaCount} Active Rule" . ($activeSlaCount !== 1 ? 's' : '') : 'None',
                'notificationsEnabled' => $enabledTypes > 0 ? "{$enabledTypes} Type" . ($enabledTypes !== 1 ? 's' : '') . ' Enabled' : 'None',
                'announcementsCount'   => $announcementsCount,
                'libraryDocsCount'     => $libraryDocsCount,
                'auditLogsCount'       => $auditLogsCount,
                'language'             => $language,
                'timezone'             => $timezone,
            ];
        });

        return response()->json($data);
    }
}
