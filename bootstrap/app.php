<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

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
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->alias([
            '2fa_or_superadmin' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'etag.cache' => \App\Http\Middleware\EtagCache::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'approved' => \App\Http\Middleware\CheckStatus::class,
            'crud' => \App\Http\Middleware\CrudPermission::class,
            'crud_permission' => \App\Http\Middleware\CrudPermission::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\BreadcrumbTrail::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
