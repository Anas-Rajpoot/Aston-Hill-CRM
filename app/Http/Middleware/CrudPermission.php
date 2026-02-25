<?php

namespace App\Http\Middleware;

use App\Support\RbacPermission;
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

        // Resource action inferred from route
        $action = $request->route()?->getActionMethod(); // index, show, create, store, edit, update, destroy

        // Map controller action -> permission action
        $map = [
            'index'   => 'read',
            'show'    => 'read',
            'create'  => 'create',
            'store'   => 'create',
            'edit'    => 'update',
            'update'  => 'update',
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

        $legacy = match ($permAction) {
            'read' => ["{$module}.list", "{$module}.view"],
            'create' => ["{$module}.create", "{$module}.add"],
            'update' => ["{$module}.edit", "{$module}.update"],
            'delete' => ["{$module}.delete"],
            default => [],
        };

        if (! RbacPermission::can($user, $module, $permAction, $legacy)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
