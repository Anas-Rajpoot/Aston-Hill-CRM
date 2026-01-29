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

Route::middleware(['auth:sanctum'])->group(function () {

  Route::prefix('lead-submissions')->group(function () {

    Route::get('/', [Api\LeadSubmissionController::class, 'index']);
    Route::post('/datatable', [Api\LeadSubmissionController::class, 'datatable']);

    // Wizard
    Route::post('/step-1', [Api\LeadSubmissionController::class, 'storeStep1']);
    Route::post('/{lead}/step-2', [Api\LeadSubmissionController::class, 'storeStep2']);
    Route::post('/{lead}/step-3', [Api\LeadSubmissionController::class, 'storeStep3']);
    Route::post('/{lead}/step-4', [Api\LeadSubmissionController::class, 'storeStep4']);

    Route::post('/{lead}/submit', [Api\LeadSubmissionController::class, 'submit']);

    Route::get('/service-types', [Api\LeadSubmissionController::class, 'serviceTypes']);
    Route::get('/type-schema/{type}', [Api\LeadSubmissionController::class, 'typeSchema']);

  });

});
