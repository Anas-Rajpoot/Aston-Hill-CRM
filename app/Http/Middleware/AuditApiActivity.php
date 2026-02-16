<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically records all authenticated POST / PUT / PATCH / DELETE API
 * requests to the audit_logs table so that every system activity appears
 * on the Audit Logs page.
 *
 * GET / HEAD / OPTIONS requests are skipped (read-only).
 * Certain noisy endpoints (polling, CSRF) are excluded.
 */
class AuditApiActivity
{
    /** Routes (or prefixes) that should NOT be logged. */
    private const EXCLUDED = [
        'sanctum/csrf-cookie',
        'api/notifications/poll',
        'api/audit-logs',
        'api/auth/',
        'api/change-password',
        'api/table-preferences',
        'broadcasting/auth',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $this->recordIfNeeded($request, $response, $start);

        return $response;
    }

    private function recordIfNeeded(Request $request, Response $response, float $start): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return;
        }

        if (! $request->user()) {
            return;
        }

        $path = $request->path();
        foreach (self::EXCLUDED as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $status = $response->getStatusCode();
        $result = $status >= 200 && $status < 400 ? 'success' : 'failure';

        $action = $this->resolveAction($request);
        $module = $this->resolveModule($request);

        $recordId  = $this->resolveRecordId($request, $response);
        $recordRef = $this->resolveRecordRef($request, $response);

        $oldValues = null;
        $newValues = null;

        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $newValues = $request->except(['password', 'password_confirmation', '_token', '_method']);
        }

        try {
            $responseData = method_exists($response, 'getData')
                ? $response->getData(true)
                : null;

            if (is_array($responseData) && isset($responseData['data'])) {
                $data = $responseData['data'];
                if (is_array($data) && isset($data['id'])) {
                    $recordId = $recordId ?: $data['id'];
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        $latency = round((microtime(true) - $start) * 1000);

        AuditLogger::record([
            'action'     => $action,
            'module'     => $module,
            'record_id'  => $recordId,
            'record_ref' => $recordRef,
            'result'     => $result,
            'latency_ms' => $latency,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
        ]);
    }

    private function resolveAction(Request $request): string
    {
        $method = $request->method();
        $path   = $request->path();

        if (str_contains($path, '/login'))   return 'login';
        if (str_contains($path, '/logout'))  return 'logout';
        if (str_contains($path, '/reset'))   return 'password_reset';
        if (str_contains($path, '/export'))  return 'exported_data';
        if (str_contains($path, '/assign'))  return 'assigned';
        if (str_contains($path, '/duplicate')) return 'created';
        if (str_contains($path, '/acknowledge')) return 'updated';
        if (str_contains($path, '/import'))  return 'created';

        return match ($method) {
            'POST'   => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default  => strtolower($method),
        };
    }

    private function resolveModule(Request $request): string
    {
        $path = $request->path();
        $path = preg_replace('#^api/#', '', $path);

        $segments = explode('/', $path);
        $base = $segments[0] ?? 'unknown';

        $map = [
            'auth'                     => 'Authentication',
            'users'                    => 'User Management',
            'roles'                    => 'Role Management',
            'permissions'              => 'Permission Management',
            'lead-submissions'         => 'Lead Submissions',
            'field-submissions'        => 'Field Submissions',
            'customer-support'         => 'Customer Support',
            'vas-requests'             => 'VAS Requests',
            'accounts'                 => 'Accounts',
            'clients'                  => 'Clients',
            'expenses'                 => 'Expenses',
            'announcements'            => 'Announcements',
            'personal-notes'           => 'Personal Notes',
            'email-followups'          => 'Email Follow-ups',
            'security-settings'        => 'Security Settings',
            'system-preferences'       => 'System Preferences',
            'sla'                      => 'SLA Rules',
            'notification-configs'     => 'Notification Config',
            'email-templates'          => 'Email Templates',
            'escalation-levels'        => 'Escalation Levels',
            'library'                  => 'Document Library',
            'cisco-extensions'         => 'Cisco Extensions',
            'employees'                => 'Employees',
            'dsp-tracker'              => 'DSP Tracker',
            'gsm-tracker'              => 'GSM Tracker',
            'change-password'          => 'Password Change',
            'me'                       => 'User Profile',
            'bootstrap'                => 'System',
            'super-admin'              => 'Super Admin',
        ];

        if (isset($map[$base])) return $map[$base];

        if (count($segments) >= 2 && isset($map[$segments[0] . '/' . $segments[1]])) {
            return $map[$segments[0] . '/' . $segments[1]];
        }

        return str_replace('-', ' ', ucfirst($base));
    }

    private function resolveRecordId(Request $request, Response $response): ?int
    {
        $params = $request->route()?->parameters() ?? [];
        foreach ($params as $val) {
            if (is_numeric($val)) return (int) $val;
            if (is_object($val) && method_exists($val, 'getKey')) return $val->getKey();
        }
        return null;
    }

    private function resolveRecordRef(Request $request, Response $response): ?string
    {
        $params = $request->route()?->parameters() ?? [];
        foreach ($params as $val) {
            if (is_numeric($val)) return (string) $val;
            if (is_object($val) && method_exists($val, 'getKey')) return (string) $val->getKey();
        }
        return null;
    }
}
