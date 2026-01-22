<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BreadcrumbTrail
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    public function handle(Request $request, Closure $next)
    {
        if (
            $request->isMethod('get') &&
            !$request->ajax() &&
            !$request->wantsJson()
        ) {
            $trail = session()->get('breadcrumbs_trail', []);

            $current = [
                'label' => $this->labelFromRequest($request),
                'url'   => $request->fullUrl(),
            ];

            // Avoid duplicates (refresh / same page)
            if (!empty($trail)) {
                $last = end($trail);
                if (($last['url'] ?? null) === $current['url']) {
                    return $next($request);
                }
            }

            // If user jumped from a totally different flow,
            // keep trail short by limiting to last 6 items
            $trail[] = $current;
            $trail = array_slice($trail, -6);

            session()->put('breadcrumbs_trail', $trail);
        }

        return $next($request);
    }

    private function labelFromRequest(Request $request): string
    {
        // Best: use route name -> nice title
        $name = optional($request->route())->getName();

        if ($name) {
            // convert route name to readable label
            // e.g. super-admin.roles.permissions.edit => Roles / Permissions
            $parts = explode('.', $name);

            // remove common prefixes
            $parts = array_values(array_filter($parts, fn($p) => !in_array($p, ['super-admin'])));

            // last part often "index/create/edit/show"
            $map = [
                'index' => 'List',
                'create' => 'Create',
                'edit' => 'Edit',
                'show' => 'View',
                'permissions' => 'Permissions',
            ];

            $pretty = array_map(function ($p) use ($map) {
                $p = $map[$p] ?? $p;
                return ucfirst(str_replace('-', ' ', $p));
            }, $parts);

            return implode(' / ', $pretty);
        }

        // fallback: URL path
        return ucfirst(trim(str_replace('-', ' ', $request->path()), '/')) ?: 'Home';
    }
}
