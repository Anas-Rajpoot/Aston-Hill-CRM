<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            \Illuminate\Support\Facades\Route::middleware('spa_shell')
                ->group(base_path('routes/spa.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        // Minimal stack for SPA document requests: no auth, no CSRF, no BreadcrumbTrail. Auth deferred to Vue + API.
        $middleware->group('spa_shell', [
            \App\Http\Middleware\ApplySecuritySettings::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->alias([
            '2fa_or_superadmin' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'etag.cache' => \App\Http\Middleware\EtagCache::class,
            'api.cache' => \App\Http\Middleware\ApiCacheHeaders::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'approved' => \App\Http\Middleware\CheckStatus::class,
            'crud' => \App\Http\Middleware\CrudPermission::class,
            'crud_permission' => \App\Http\Middleware\CrudPermission::class,
        ]);
        $middleware->web(prepend: [
            \App\Http\Middleware\ApplySecuritySettings::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\BreadcrumbTrail::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\ApplySecuritySettings::class,
            \App\Http\Middleware\ValidateSessionToken::class,
            \App\Http\Middleware\EnforcePasswordExpiry::class,
            \App\Http\Middleware\AuditApiActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApiRequest = static function (Request $request): bool {
            return $request->expectsJson() || $request->is('api/*');
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'Unauthorized. Please login first.',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'Request failed. Please contact support if the issue persists.',
            ], 500);
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = match ($status) {
                404 => 'Requested resource was not found.',
                405 => 'This action is not allowed.',
                419 => 'Session expired. Please refresh and try again.',
                429 => 'Too many requests. Please try again shortly.',
                default => 'Something went wrong. Please try again.',
            };

            return response()->json([
                'status' => 'fail',
                'message' => $message,
            ], $status);
        });
    })->create();
