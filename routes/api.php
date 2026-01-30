<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
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
use App\Http\Controllers\ColumnPreferenceController;
use App\Http\Controllers\LeadSubmissionController;
use App\Http\Controllers\DataTableController;
use App\Http\Controllers\FieldSubmissionController;
use Illuminate\Http\Request;

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user()->load('roles');
});

Route::middleware(['auth:sanctum'])->group(function () {

  Route::prefix('lead-submissions')->name('lead-submissions.')->group(function () {

    Route::get('/', [LeadSubmissionController::class, 'index'])->name('index');
    Route::post('/datatable', [LeadSubmissionController::class, 'datatable'])->name('datatable');

    // Wizard
    Route::post('/step-1', [LeadSubmissionController::class, 'storeStep1'])->name('store.step1');
    Route::post('/{lead}/step-2', [LeadSubmissionController::class, 'storeStep2'])->name('store.step2');
    Route::post('/{lead}/step-3', [LeadSubmissionController::class, 'storeStep3'])->name('store.step3');
    Route::post('/{lead}/step-4', [LeadSubmissionController::class, 'storeStep4'])->name('store.step4');

    Route::post('/{lead}/submit', [LeadSubmissionController::class, 'submit']);

    Route::get('/service-types', [LeadSubmissionController::class, 'serviceTypes'])->name('serviceTypesByCategory');
    Route::get('/type-schema/{type}', [LeadSubmissionController::class, 'typeSchema'])->name('typeSchema');

  });

  Route::prefix('field-submissions')->group(function () {
    Route::get('/team-options', [FieldSubmissionController::class, 'teamOptions'])->name('field-submissions.team-options');
    Route::post('/', [FieldSubmissionController::class, 'store'])->name('field-submissions.store');
  });

});
