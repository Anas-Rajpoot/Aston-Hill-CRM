<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BreadcrumbTrail
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->shouldTrack($request)) {
            return $next($request);
        }

        $tabId = $this->getTabId($request);
        if (!$tabId) {
            return $next($request); // if no tab id, skip tracking
        }

        $label = $this->labelFromRequest($request);
        if ($label === '') {
            return $next($request);
        }

        $url = (string) $request->fullUrl();

        $sessionKey = "breadcrumbs_trail.$tabId";
        $trail = session()->get($sessionKey, []);

        $current = ['label' => $label, 'url' => $url];

        // ✅ Reset on root pages (sidebar entry points)
        if ($request->routeIs('dashboard', 'super-admin.roles.index', 'accounts.index', 'expenses.index')) {
            session()->put($sessionKey, [$current]);
            return $next($request);
        }

        // ✅ Remove duplicates by URL and label
        $trail = array_values(array_filter($trail, fn($item) => ($item['url'] ?? null) !== $url));
        $trail = array_values(array_filter($trail, fn($item) => ($item['label'] ?? null) !== $label));

        $trail[] = $current;
        $trail = array_slice($trail, -8);

        session()->put($sessionKey, $trail);

        return $next($request);
    }

    private function getTabId(Request $request): ?string
    {
        // 1) query param (best)
        $tabId = $request->query('__tab');

        // 2) cookie fallback (for refresh/direct access)
        if (!$tabId) {
            $tabId = $request->cookie('__tab');
        }

        if (!$tabId || strlen($tabId) > 80) return null;

        return $tabId;
    }


    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('get')) {
            return false;
        }
        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }
        if ($request->routeIs('login', 'register', 'password.*')) {
            return false;
        }
        if (! $request->route()) {
            return false;
        }
        // Skip SPA document routes (unnamed closure returning view) – no session read/write for faster first byte.
        if ($request->route()->getName() === null) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        if (str_starts_with($path, '.well-known')) {
            return false;
        }
        if (str_starts_with($path, 'build/') || str_starts_with($path, 'storage/') || str_starts_with($path, 'vendor/')) {
            return false;
        }

        return true;
    }

    private function labelFromRequest(Request $request): string
    {
        $name = $request->route()?->getName();
        if (!$name) return '';

        $map = [
            'dashboard' => 'Dashboard',
            'super-admin.roles.index' => 'Roles',
            'super-admin.roles.permissions.edit' => 'Roles / Permissions',
            'expenses.index' => 'Expenses',
            'expenses.create' => 'Expenses / Create',
        ];

        return $map[$name] ?? ucfirst(str_replace(['.', '-'], [' / ', ' '], $name));
    }
}
