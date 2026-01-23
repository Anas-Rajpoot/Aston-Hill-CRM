<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SuperAdmin\RoleController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\SuperAdmin\PermissionController;
use App\Http\Controllers\ExpenseController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'approved', '2fa_or_superadmin'])->name('dashboard');

Route::get('super-admin/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', SuperAdminMiddleware::class])->name('super-admin.dashboard');

Route::middleware(['auth', 'verified', 'approved', '2fa_or_superadmin'])->group(function () {
    
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

});


Route::middleware(['auth','role:superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::resource('users', UserController::class);

    Route::put('users/{user}/approve', [UserController::class,'approve'])
        ->name('users.approve');
    
    Route::get('/users/{user}/review', [UserController::class, 'review'])
        ->name('users.review');

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

    Route::get('/2fa/verify', [TwoFactorController::class, 'verifyForm'])->name('2fa.verify.form');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify');
});


Route::get('/.well-known/{any}', function () {
    return response()->noContent();
})->where('any', '.*');

require __DIR__.'/auth.php';
