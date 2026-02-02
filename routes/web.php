<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\SuperAdmin\PermissionController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PersonalNoteController;
use App\Http\Controllers\EmailFollowUpController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\NotificationController;

// Sanctum CSRF cookie (required for session-based SPA auth)
Route::get('/sanctum/csrf-cookie', [\Laravel\Sanctum\Http\Controllers\CsrfCookieController::class, 'show']);

// Serve Vue SPA for root (Vue router handles auth and redirects guests to /login)
Route::get('/', fn () => view('layouts.app'));

// Serve Vue SPA for dashboard (Vue handles the UI)
Route::get('/dashboard', fn () => view('layouts.app'))
    ->middleware(['auth', 'verified', 'approved', '2fa_or_superadmin'])->name('dashboard');

Route::get('super-admin/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', SuperAdminMiddleware::class])->name('super-admin.dashboard');

Route::middleware(['auth', 'verified', 'approved', '2fa_or_superadmin'])->group(function () {

    // Vue SPA routes – serve layouts.app so #app exists (Vue Router handles the rest)
    Route::get('/users', fn () => view('layouts.app'));
    Route::get('/users/create', fn () => view('layouts.app'));
    Route::get('/users/{user}', fn () => view('layouts.app'))->whereNumber('user');
    Route::get('/users/{user}/edit', fn () => view('layouts.app'))->whereNumber('user');
    Route::get('/submissions', fn () => view('layouts.app'));
    Route::get('/lead-submissions', fn () => view('layouts.app'));
    Route::get('/announcements', fn () => view('layouts.app'));
    Route::get('/announcements/create', fn () => view('layouts.app'));
    Route::get('/announcements/{announcement}', fn () => view('layouts.app'));
    Route::get('/announcements/{announcement}/edit', fn () => view('layouts.app'));
    Route::get('/notifications', fn () => view('layouts.app'));
    Route::get('/accounts', fn () => view('layouts.app'));
    Route::get('/accounts/create', fn () => view('layouts.app'));
    Route::get('/accounts/{account}', fn () => view('layouts.app'));
    Route::get('/accounts/{account}/edit', fn () => view('layouts.app'));
    Route::get('/expenses', fn () => view('layouts.app'));
    Route::get('/expenses/create', fn () => view('layouts.app'));
    Route::get('/expenses/{expense}', fn () => view('layouts.app'));
    Route::get('/expenses/{expense}/edit', fn () => view('layouts.app'));
    Route::get('/personal-notes', fn () => view('layouts.app'));
    Route::get('/personal-notes/create', fn () => view('layouts.app'));
    Route::get('/personal-notes/{personal_note}', fn () => view('layouts.app'));
    Route::get('/personal-notes/{personal_note}/edit', fn () => view('layouts.app'));
    Route::get('/email-followups', fn () => view('layouts.app'));
    Route::get('/email-followups/create', fn () => view('layouts.app'));
    Route::get('/email-followups/{email_followup}', fn () => view('layouts.app'));
    Route::get('/email-followups/{email_followup}/edit', fn () => view('layouts.app'));
    Route::get('/login-logs', fn () => view('layouts.app'));
    Route::get('/login-logs/timeline/{user}', fn () => view('layouts.app'));
    Route::get('/back-office', fn () => view('layouts.app'));
    Route::get('/field-head', fn () => view('layouts.app'));
    Route::get('/customer-support', fn () => view('layouts.app'));
    Route::get('/vas-requests', fn () => view('layouts.app'));
    Route::get('/clients', fn () => view('layouts.app'));
    Route::get('/gsm-tracker', fn () => view('layouts.app'));
    Route::get('/employees', fn () => view('layouts.app'));
    Route::get('/attendance-log', fn () => view('layouts.app'));
    Route::get('/reports', fn () => view('layouts.app'));
    Route::get('/settings', fn () => view('layouts.app'));
    Route::get('/settings/team-hierarchy', fn () => view('layouts.app'));

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('accounts', AccountController::class)->middleware('crud_permission:accounts');

    Route::get('login-logs', [LoginLogController::class, 'index'])->name('login-logs.index');
    Route::get('login-logs/datatable', [LoginLogController::class, 'datatable'])->name('login-logs.datatable');

    Route::get('login-logs/export/csv', [LoginLogController::class, 'exportCsv'])->name('login-logs.export.csv');

    Route::get('login-logs/timeline/{user}', [LoginLogController::class, 'timeline'])->name('login-logs.timeline');

    Route::post('login-logs/force-logout/log/{log}', [LoginLogController::class, 'forceLogoutLog'])->name('login-logs.force-logout-log');
    Route::post('login-logs/force-logout/user/{user}', [LoginLogController::class, 'forceLogoutUser'])->name('login-logs.force-logout-user');

    Route::get('/expenses/datatable', [ExpenseController::class, 'datatable'])->name('expenses.datatable');
    Route::get('/expenses/export/csv', [ExpenseController::class, 'exportCsv'])->name('expenses.export.csv');
    Route::get('/expenses/{expense}/export/csv', [ExpenseController::class, 'exportSingleCsv'])->name('expenses.export.single');

    Route::resource('expenses', ExpenseController::class);

    Route::get('personal-notes/datatable', [PersonalNoteController::class, 'datatable'])
    ->name('personal-notes.datatable');

    Route::put('personal-notes/{personal_note}/toggle', [PersonalNoteController::class, 'toggle'])
        ->name('personal-notes.toggle');

    Route::resource('personal-notes', PersonalNoteController::class);

    Route::get('email-followups/datatable', [EmailFollowUpController::class, 'datatable'])
    ->name('email-followups.datatable');

    Route::get('email-followups/export/csv', [EmailFollowUpController::class, 'exportCsv'])
        ->name('email-followups.export.csv');

    Route::resource('email-followups', EmailFollowUpController::class);

    // Users – API handles CRUD (see api.php), Vue SPA handles UI

    Route::get('announcements/datatable', [AnnouncementController::class, 'datatable'])
    ->name('announcements.datatable');

    Route::resource('announcements', AnnouncementController::class);

    Route::get('/notifications', [NotificationController::class, 'index'])
    ->name('notifications.index');

    Route::get('/notifications/datatable', [NotificationController::class, 'datatable'])
    ->name('notifications.datatable');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
    ->name('notifications.read');

    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
    
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    Route::get('/notifications/poll', [NotificationController::class, 'poll'])
    ->name('notifications.poll');

    // Route::prefix('lead-submissions')->name('lead-submissions.')->group(function () {

    //     Route::get('/', [LeadSubmissionController::class, 'index'])->name('index');
    //     Route::get('/datatable', [LeadSubmissionController::class, 'datatable'])->name('datatable');

    //     // Wizard
    //     Route::get('/create/step-1', [LeadSubmissionController::class, 'createStep1'])->name('create.step1');
    //     Route::post('/create/step-1', [LeadSubmissionController::class, 'storeStep1'])->name('store.step1');

    //     Route::get('/create/step-2', [LeadSubmissionController::class, 'createStep2'])->name('create.step2');
    //     Route::post('/create/step-2', [LeadSubmissionController::class, 'storeStep2'])->name('store.step2');

    //     Route::get('/create/step-3', [LeadSubmissionController::class, 'createStep3'])->name('create.step3');
    //     Route::post('/create/step-3', [LeadSubmissionController::class, 'storeStep3'])->name('store.step3');

    //     Route::get('/create/step-4', [LeadSubmissionController::class, 'createStep4'])->name('create.step4');
    //     Route::post('/create/step-4', [LeadSubmissionController::class, 'storeStep4'])->name('store.step4');

    //     Route::get('/service-types', [LeadSubmissionController::class, 'serviceTypesByCategory'])
    //         ->name('serviceTypesByCategory');
    //     Route::get('/type-schema/{type}', [LeadSubmissionController::class, 'typeSchema'])
    //         ->name('typeSchema');

    //     Route::post('/preferences/columns', [LeadSubmissionController::class, 'saveColumnPrefs'])
    //         ->name('preferences.columns');

    //     // Resource CRUD
    //     Route::get('/{lead_submission}', [LeadSubmissionController::class, 'show'])->name('show');
    //     Route::get('/{lead_submission}/edit', [LeadSubmissionController::class, 'edit'])->name('edit');
    //     Route::put('/{lead_submission}', [LeadSubmissionController::class, 'update'])->name('update');
    //     Route::delete('/{lead_submission}', [LeadSubmissionController::class, 'destroy'])->name('destroy');

    //     Route::get('/{lead_submission}/documents/{document}/download',
    //             [LeadSubmissionController::class, 'downloadDocument']
    //         )->name('documents.download');

    // });

    });


Route::middleware(['auth','role:superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {

    // Vue SPA routes for super-admin
    Route::get('/roles', fn () => view('layouts.app'));
    Route::get('/roles/create', fn () => view('layouts.app'));
    Route::get('/roles/{role}', fn () => view('layouts.app'));
    Route::get('/roles/{role}/edit', fn () => view('layouts.app'));
    Route::get('/roles/{role}/permissions', fn () => view('layouts.app'));
    Route::get('/permissions', fn () => view('layouts.app'));
    Route::get('/permissions/create', fn () => view('layouts.app'));
    Route::get('/permissions/{permission}', fn () => view('layouts.app'));
    Route::get('/permissions/{permission}/edit', fn () => view('layouts.app'));

    // User edit/approval – handled via API PUT /users/{user}

    // Route::post('/admin/users/{user}/approve', [UserController::class, 'update'])
    //     ->name('admin.users.update');

    Route::resource('roles', RoleController::class);
    Route::get('roles/{role}/permissions', [RoleController::class, 'editPermissions'])->name('roles.permissions.edit');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::put('roles/{role}/permissions/module/{module}', [RoleController::class, 'updatePermissionsModule'])
    ->name('roles.permissions.updateModule');

    Route::resource('permissions', PermissionController::class);
});

// 2FA routes
Route::middleware(['auth'])->group(function () {
    Route::get('/2fa/setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

    // Serve Vue SPA for 2FA verify page
    Route::get('/2fa/verify', fn () => view('layouts.app'))->name('2fa.verify.form');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify')->middleware('throttle:5,1');
});


Route::get('/.well-known/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

require __DIR__.'/auth.php';
