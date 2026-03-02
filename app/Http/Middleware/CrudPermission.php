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
            // Wizard/custom actions used across modules
            'storeStep1' => 'create',
            'storeStep2' => 'update',
            'storeStep3' => 'update',
            'storeStep4' => 'update',
            'updateStep1' => 'update',
            'resubmissionData' => 'read',
            'resubmit' => 'update',
            'discardDraft' => 'delete',
            'filters' => 'read',
            'columns' => 'read',
            'saveColumns' => 'update',
            'bootstrap' => 'read',
            'teamOptions' => 'create',
            // Used by lead creation wizard; should follow create permission, not listing permission.
            'currentDraft' => 'create',
            'auditLog' => 'read',
            'audits' => 'read',
            'backOfficeOptions' => 'read',
            'bulkAssign' => 'update',
            'bulkAssignStatus' => 'read',
            'updateStatus' => 'update',
            'updateStatusChangedAt' => 'update',
            'updateBackOffice' => 'update',
            'uploadDocuments' => 'update',
            'deleteDocument' => 'delete',
        ];

        $permAction = $map[$action] ?? null;

        // Fallback for any custom controller methods not explicitly mapped.
        if (!$permAction) {
            $permAction = match (strtoupper($request->method())) {
                'GET', 'HEAD', 'OPTIONS' => 'read',
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => null,
            };
        }

        if (!$permAction) {
            abort(403, 'Unauthorized');
        }

        $moduleCandidates = $this->moduleCandidates($module);
        $legacy = [];
        foreach ($moduleCandidates as $candidate) {
            $legacy = array_merge($legacy, match ($permAction) {
                'read' => ["{$candidate}.list", "{$candidate}.view"],
                'create' => ["{$candidate}.create", "{$candidate}.add"],
                'update' => ["{$candidate}.edit", "{$candidate}.update"],
                'delete' => ["{$candidate}.delete"],
                default => [],
            });
        }
        $legacy = array_values(array_unique($legacy));

        if (! RbacPermission::can($user, $moduleCandidates, $permAction, $legacy)) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }

    /**
     * Build module aliases to support legacy key formats.
     *
     * @return array<int,string>
     */
    private function moduleCandidates(string $module): array
    {
        $normalized = trim($module);
        $aliases = [
            $normalized,
            str_replace('-', '_', $normalized),
            str_replace('_', '-', $normalized),
        ];

        // Explicit backwards-compatible aliases for submission modules.
        if (in_array($normalized, ['lead-submissions', 'lead_submissions'], true)) {
            $aliases = array_merge($aliases, ['lead', 'lead-submission', 'lead_submission']);
        }
        if (in_array($normalized, ['field-submissions', 'field_submissions'], true)) {
            $aliases = array_merge($aliases, ['field', 'field-submission', 'field_submission']);
        }

        return array_values(array_unique($aliases));
    }
}
