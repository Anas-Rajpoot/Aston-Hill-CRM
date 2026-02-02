<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ColumnPreferenceController;
use App\Http\Controllers\DataTableController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FieldSubmissionController;
use App\Http\Controllers\LeadSubmissionController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalNoteController;
use App\Http\Controllers\EmailFollowUpController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\SuperAdmin\TeamRoleMappingController;
use App\Http\Controllers\Api\UserController;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes – Framework-agnostic, supports session + token auth
|--------------------------------------------------------------------------
| All JSON endpoints live here. Use with Vue, React, Angular, or mobile.
| - Session: Same-origin SPA, withCredentials + CSRF
| - Token: Bearer token in Authorization header (e.g. separate frontend deploy)
*/

// ----- Public -----
Route::get('/countries', fn () => Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'timezone']));

// ----- Auth (guest) – login needs session for same-origin SPA -----
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('web');

// ----- Protected (auth:sanctum = session OR token) – web middleware for session auth -----
Route::middleware(['web', 'auth:sanctum', 'verified', 'approved', '2fa_or_superadmin'])->group(function () {

    Route::get('/me', fn (Request $request) => $request->user()->load('roles'));

    // Field submissions
    Route::get('/field-submissions/team-options', [FieldSubmissionController::class, 'teamOptions']);
    Route::post('/field-submissions', [FieldSubmissionController::class, 'store']);

    // Lead submissions (specific routes before {lead})
    Route::get('/lead-submissions/current-draft', [LeadSubmissionController::class, 'currentDraft']);
    Route::get('/lead-submissions/categories', [LeadSubmissionController::class, 'categories']);
    Route::get('/lead-submissions/service-types', [LeadSubmissionController::class, 'serviceTypes']);
    Route::get('/lead-submissions/type-schema/{type}', [LeadSubmissionController::class, 'typeSchema']);
    Route::post('/lead-submissions/step-1', [LeadSubmissionController::class, 'storeStep1']);
    Route::get('/lead-submissions/{lead}', [LeadSubmissionController::class, 'show'])->whereNumber('lead');
    Route::put('/lead-submissions/{lead}/step-1', [LeadSubmissionController::class, 'updateStep1']);
    Route::delete('/lead-submissions/{lead}/discard', [LeadSubmissionController::class, 'discardDraft']);
    Route::post('/lead-submissions/{lead}/step-2', [LeadSubmissionController::class, 'storeStep2']);
    Route::post('/lead-submissions/{lead}/step-3', [LeadSubmissionController::class, 'storeStep4']); // step 3 in UI = documents
    Route::post('/lead-submissions/{lead}/step-4', [LeadSubmissionController::class, 'storeStep4']); // backward compatibility
    Route::post('/lead-submissions/{lead}/submit', [LeadSubmissionController::class, 'submit']);

    // Column preferences
    Route::get('/modules/{module}/columns', [ColumnPreferenceController::class, 'show']);
    Route::post('/modules/{module}/columns', [ColumnPreferenceController::class, 'store']);

    // Users (list, show, update, delete, create – super admin / authorized)
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/users/bulk-activate', [UserController::class, 'bulkActivate']);
    Route::post('/users/bulk-deactivate', [UserController::class, 'bulkDeactivate']);
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user');
    Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->whereNumber('user');

    // Datatable
    Route::get('/datatable/{module}', [DataTableController::class, 'index']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/datatable', [NotificationController::class, 'datatable']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{id}/unread', [NotificationController::class, 'markUnread']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/poll', [NotificationController::class, 'poll']);
});

// ----- Auth actions (need auth but no 2fa for logout) -----
Route::middleware(['web', 'auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/2fa/verify', [AuthController::class, 'verify2FA'])->name('api.auth.2fa.verify')->middleware('throttle:5,1');
});

// ----- Super admin only -----
Route::middleware(['web', 'auth:sanctum', 'verified', 'approved', '2fa_or_superadmin', 'role:superadmin'])->prefix('super-admin')->group(function () {
    Route::get('/team-role-mappings', [TeamRoleMappingController::class, 'index']);
    Route::put('/team-role-mappings', [TeamRoleMappingController::class, 'update']);
});
