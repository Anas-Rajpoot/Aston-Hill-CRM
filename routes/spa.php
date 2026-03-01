<?php

/**
 * SPA shell routes – minimal middleware (spa_shell) for fast initial HTML.
 * Auth, verified, approved, 2FA are enforced by Vue router + API; no server-side auth on these GETs.
 */

use Illuminate\Support\Facades\Route;

$spa = fn () => view('layouts.app');

// Fast static response so /favicon.ico doesn't trigger view render (when request hits Laravel)
Route::get('favicon.ico', fn () => response()->file(public_path('favicon.ico'), ['Cache-Control' => 'public, max-age=86400']));

Route::get('/', $spa);

Route::get('/dashboard', $spa)->name('dashboard');

Route::get('/permissions', $spa)->name('app.permissions');

Route::get('/users', $spa);
Route::get('/users/create', $spa);
Route::get('/users/{user}', $spa)->whereNumber('user');
Route::get('/users/{user}/edit', $spa)->whereNumber('user');
Route::get('/teams', $spa);
Route::get('/teams/create', $spa);
Route::get('/teams/{id}', $spa)->whereNumber('id');
Route::get('/teams/{id}/edit', $spa)->whereNumber('id');
Route::get('/teams/{id}/members', $spa)->whereNumber('id');

Route::get('/submissions', $spa);
Route::get('/lead-submissions', $spa);
Route::get('/lead-submissions/{id}/resubmit', $spa)->whereNumber('id');
Route::get('/lead-submissions/{id}/edit', $spa)->whereNumber('id');
Route::get('/lead-submissions/{id}', $spa)->whereNumber('id');

Route::get('/announcements', $spa);
Route::get('/announcements/create', $spa);
Route::get('/announcements/{announcement}', $spa);
Route::get('/announcements/{announcement}/edit', $spa);

Route::get('/notifications', $spa);

Route::get('/accounts', $spa);
Route::get('/accounts/create', $spa);
Route::get('/accounts/{account}', $spa);
Route::get('/accounts/{account}/edit', $spa);

Route::get('/expenses', $spa);
Route::get('/expenses/create', $spa);
Route::get('/expenses/{expense}', $spa);
Route::get('/expenses/{expense}/edit', $spa);

Route::get('/personal-notes', $spa);
Route::get('/personal-notes/create', $spa);
Route::get('/personal-notes/{personal_note}', $spa);
Route::get('/personal-notes/{personal_note}/edit', $spa);

Route::get('/email-followups', $spa);
Route::get('/email-followups/create', $spa);
Route::get('/email-followups/{email_followup}', $spa);
Route::get('/email-followups/{email_followup}/edit', $spa);

Route::get('/login-logs', $spa)->name('login-logs.index');
Route::get('/login-logs/timeline/{user}', $spa);

Route::get('/back-office', $spa);
Route::get('/field-submissions', $spa);
Route::get('/field-submissions/{id}/edit', $spa)->whereNumber('id');
Route::get('/field-submissions/{id}', $spa)->whereNumber('id');
Route::get('/customer-support', $spa);
Route::get('/customer-support/{id}/edit', $spa)->whereNumber('id');
Route::get('/customer-support/{id}', $spa)->whereNumber('id');
Route::get('/vas-requests', $spa);
Route::get('/vas-requests/{id}/edit', $spa)->whereNumber('id');
Route::get('/vas-requests/{id}', $spa)->whereNumber('id');
Route::get('/special-requests', $spa);
Route::get('/special-requests/{id}', $spa)->whereNumber('id');
Route::get('/special-requests/{id}/edit', $spa)->whereNumber('id');
Route::get('/order-status', $spa);
Route::get('/all-clients', $spa);
Route::get('/clients', $spa);
Route::get('/clients/create', $spa);
Route::get('/clients/products/{id}', $spa)->whereNumber('id');
Route::get('/clients/products/{id}/edit', $spa)->whereNumber('id');
Route::get('/clients/{id}', $spa)->whereNumber('id');
Route::get('/clients/{id}/edit', $spa)->whereNumber('id');
Route::get('/dsp-tracker', $spa);
Route::get('/gsm-tracker', $spa);
Route::get('/verifiers-detail', $spa);
Route::get('/employees', $spa);
Route::get('/employees/{id}', $spa)->whereNumber('id');
Route::get('/employees/{id}/edit', $spa)->whereNumber('id');
Route::get('/cisco-extensions', $spa);
Route::get('/cisco-extensions/create', $spa);
Route::get('/cisco-extensions/{id}', $spa)->whereNumber('id');
Route::get('/cisco-extensions/{id}/edit', $spa)->whereNumber('id');
Route::get('/attendance-log', $spa);
Route::get('/reports', $spa);
Route::get('/reports/lead', $spa);
Route::get('/reports/field-operations', $spa);
Route::get('/reports/vas', $spa);
Route::get('/reports/sla', $spa);
Route::get('/settings', $spa);
Route::get('/settings/team-hierarchy', $spa);
Route::get('/settings/system-preferences', $spa);
Route::get('/settings/sla', $spa);
Route::get('/settings/notifications-email', $spa);
Route::get('/settings/announcement-center', $spa);
Route::get('/settings/library', $spa);
Route::get('/settings/data-import-export', $spa);
Route::get('/settings/security-session', $spa);
Route::get('/settings/audit-logs', $spa);

Route::get('/roles', $spa);
Route::get('/roles/create', $spa);
Route::get('/roles/{role}', $spa)->whereNumber('role');
Route::get('/roles/{role}/edit', $spa)->whereNumber('role');
Route::get('/roles/{role}/permissions', $spa)->whereNumber('role');

// Super-admin SPA routes (Vue guards; no server-side role check on document request)
Route::prefix('super-admin')->name('super-admin.')->group(function () use ($spa) {
    Route::get('/roles', $spa);
    Route::get('/roles/create', $spa);
    Route::get('/roles/{role}', $spa);
    Route::get('/roles/{role}/edit', $spa);
    Route::get('/roles/{role}/permissions', $spa);
    Route::get('/permissions', $spa);
    Route::get('/permissions/create', $spa);
    Route::get('/permissions/{permission}', $spa);
    Route::get('/permissions/{permission}/edit', $spa);
});

Route::get('/2fa/verify', $spa)->name('2fa.verify.form');
