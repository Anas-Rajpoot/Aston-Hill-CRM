<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Controllers\Auth\TwoFactorController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', 'approved', '2fa'])->name('dashboard');

Route::get('super-admin/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', SuperAdminMiddleware::class])->name('super-admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth','role:superadmin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::resource('users', UserController::class);

    Route::put('users/{user}/approve', [UserController::class,'approve'])
        ->name('users.approve');
    
    Route::get('/users/{user}/review', [UserController::class, 'review'])
        ->name('users.review');

    // Route::post('/admin/users/{user}/approve', [UserController::class, 'update'])
    //     ->name('admin.users.update');
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
