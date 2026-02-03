<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CrudPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        // If not logged in, let auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Superadmin bypass (if you used Gate::before you can keep this, it’s extra safety)
        if (method_exists($user, 'hasRole') && $user->hasRole('superadmin')) {
            return $next($request);
        }

        // Resource action inferred from route
        $action = $request->route()?->getActionMethod(); // index, show, create, store, edit, update, destroy

        // Map controller action -> permission action
        $map = [
            'index'   => 'list',
            'show'    => 'view',
            'create'  => 'create',
            'store'   => 'create',
            'edit'    => 'edit',
            'update'  => 'edit',
            'destroy' => 'delete',
            'submit'  => 'create',
        ];

        $permAction = $map[$action] ?? null;

        // If route action is not a standard resource action, just allow by default
        // (You can change this to "deny by default" if you prefer strict mode)
        if (!$permAction) {
            abort(403, 'Unauthorized');
            // return $next($request);
        }

        $permission = "{$module}.{$permAction}";

        // Spatie permission check
        if (!$user->can($permission)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
