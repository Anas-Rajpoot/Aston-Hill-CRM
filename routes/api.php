<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\ColumnPreferenceController;
use App\Http\Controllers\DataTableController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\CustomerSupportController;
use App\Http\Controllers\FieldSubmissionController;
use App\Http\Controllers\LeadSubmissionController;
use App\Http\Controllers\VasRequestController;
use App\Http\Controllers\LoginLogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalNoteController;
use App\Http\Controllers\EmailFollowUpController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\SuperAdmin\TeamRoleMappingController;
use App\Http\Controllers\Api\SuperAdmin\RoleApiController;
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

// ----- Public (cached 1h to reduce DB load) -----
Route::get('/countries', function () {
    return Cache::remember('api_countries', 3600, fn () => Country::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'timezone']));
});

// ----- Auth (guest) – login needs session for same-origin SPA -----
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('web');

// ----- Protected (auth:sanctum = session OR token) – web middleware for session auth -----
Route::middleware(['web', 'auth:sanctum', 'verified', 'approved', '2fa_or_superadmin'])->group(function () {

    Route::get('/me', MeController::class);
    Route::get('/bootstrap', \App\Http\Controllers\Api\BootstrapController::class);

    // Field submissions
    Route::get('/field-submissions/team-options', [FieldSubmissionController::class, 'teamOptions']);
    Route::post('/field-submissions', [FieldSubmissionController::class, 'store']);
    Route::get('/field-submissions', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'index']);
    Route::get('/field-submissions/filters', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'filters']);
    Route::get('/field-submissions/edit-options', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'editOptions']);
    Route::get('/field-submissions/columns', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'columns']);
    Route::post('/field-submissions/columns', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'saveColumns']);
    Route::get('/field-submissions/{fieldSubmission}/documents/{document}/download', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'downloadDocument'])
        ->whereNumber(['fieldSubmission', 'document']);
    Route::get('/field-submissions/{fieldSubmission}', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'show'])
        ->whereNumber('fieldSubmission');
    Route::put('/field-submissions/{fieldSubmission}', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'update'])
        ->whereNumber('fieldSubmission');
    Route::patch('/field-submissions/{fieldSubmission}', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'patch'])
        ->whereNumber('fieldSubmission');
    Route::patch('/field-submissions/{fieldSubmission}/status', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'updateStatus'])
        ->whereNumber('fieldSubmission');
    Route::patch('/field-submissions/{fieldSubmission}/assign-field-technician', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'assignFieldTechnician'])
        ->whereNumber('fieldSubmission');
    Route::get('/field-submissions/audit-log', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'auditLog']);

    // Customer support
    Route::get('/customer-support/team-options', [CustomerSupportController::class, 'teamOptions']);
    Route::post('/customer-support', [CustomerSupportController::class, 'store']);
    Route::get('/customer-support', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'index']);
    Route::get('/customer-support/filters', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'filters']);
    Route::get('/customer-support/columns', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'columns']);
    Route::get('/customer-support/edit-options', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'editOptions']);
    Route::post('/customer-support/columns', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'saveColumns']);
    Route::get('/customer-support/{customerSupportSubmission}', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'show'])
        ->whereNumber('customerSupportSubmission');
    Route::get('/customer-support/{customerSupportSubmission}/audits', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'audits'])
        ->whereNumber('customerSupportSubmission');
    Route::post('/customer-support/{customerSupportSubmission}/attachments', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'addAttachments'])
        ->whereNumber('customerSupportSubmission');
    Route::get('/customer-support/{customerSupportSubmission}/attachments/{index}/download', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'downloadAttachment'])
        ->whereNumber(['customerSupportSubmission', 'index']);
    Route::patch('/customer-support/{customerSupportSubmission}', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'patch'])
        ->whereNumber('customerSupportSubmission');

    // Clients
    Route::get('/clients', [\App\Http\Controllers\Api\ClientApiController::class, 'index']);
    Route::post('/clients', [\App\Http\Controllers\Api\ClientApiController::class, 'store']);
    Route::get('/clients/filters', [\App\Http\Controllers\Api\ClientApiController::class, 'filters']);
    Route::get('/clients/columns', [\App\Http\Controllers\Api\ClientApiController::class, 'columns']);
    Route::post('/clients/columns', [\App\Http\Controllers\Api\ClientApiController::class, 'saveColumns']);
    Route::post('/clients/import', [\App\Http\Controllers\Api\ClientApiController::class, 'importCsv']);
    Route::get('/clients/{client}/products', [\App\Http\Controllers\Api\ClientApiController::class, 'products'])->whereNumber('client');
    Route::get('/clients/{client}/vas-requests', [\App\Http\Controllers\Api\ClientApiController::class, 'vasRequests'])->whereNumber('client');
    Route::get('/clients/{client}/customer-support', [\App\Http\Controllers\Api\ClientApiController::class, 'customerSupport'])->whereNumber('client');
    Route::get('/clients/{client}/audits', [\App\Http\Controllers\Api\ClientApiController::class, 'audits'])->whereNumber('client');
    Route::put('/clients/{client}', [\App\Http\Controllers\Api\ClientApiController::class, 'update'])->whereNumber('client');
    Route::put('/clients/{client}/company-details', [\App\Http\Controllers\Api\ClientApiController::class, 'updateCompanyDetails'])->whereNumber('client');
    Route::put('/clients/{client}/contacts', [\App\Http\Controllers\Api\ClientApiController::class, 'updateContacts'])->whereNumber('client');
    Route::put('/clients/{client}/addresses', [\App\Http\Controllers\Api\ClientApiController::class, 'updateAddresses'])->whereNumber('client');
    Route::get('/clients/{client}/alerts', [\App\Http\Controllers\Api\ClientApiController::class, 'alerts'])->whereNumber('client');
    Route::get('/clients/{client}', [\App\Http\Controllers\Api\ClientApiController::class, 'show'])->whereNumber('client');

    // VAS requests
    Route::get('/vas-requests/team-options', [VasRequestController::class, 'teamOptions']);
    Route::get('/vas-requests/document-schema', [VasRequestController::class, 'documentSchemaResponse']);
    Route::post('/vas-requests/step-1', [VasRequestController::class, 'storeStep1']);
    Route::get('/vas-requests', [\App\Http\Controllers\Api\VasRequestApiController::class, 'index']);
    Route::get('/vas-requests/filters', [\App\Http\Controllers\Api\VasRequestApiController::class, 'filters']);
    Route::get('/vas-requests/columns', [\App\Http\Controllers\Api\VasRequestApiController::class, 'columns']);
    Route::post('/vas-requests/columns', [\App\Http\Controllers\Api\VasRequestApiController::class, 'saveColumns']);
    Route::get('/vas-requests/back-office-options', [\App\Http\Controllers\Api\VasRequestApiController::class, 'backOfficeOptions']);
    Route::post('/vas-requests/bulk-assign', [\App\Http\Controllers\Api\VasRequestApiController::class, 'bulkAssign']);
    Route::get('/vas-requests/{vasRequest}', [VasRequestController::class, 'show'])->whereNumber('vasRequest');
    Route::get('/vas-requests/{vasRequest}/documents/{document}/download', [VasRequestController::class, 'downloadDocument'])->whereNumber('vasRequest')->whereNumber('document');
    Route::delete('/vas-requests/{vasRequest}/documents/{document}', [VasRequestController::class, 'deleteDocument'])->whereNumber('vasRequest')->whereNumber('document');
    Route::put('/vas-requests/{vasRequest}', [VasRequestController::class, 'update'])->whereNumber('vasRequest');
    Route::patch('/vas-requests/{vasRequest}', [\App\Http\Controllers\Api\VasRequestApiController::class, 'patch'])->whereNumber('vasRequest');
    Route::post('/vas-requests/{vasRequest}/step-2', [VasRequestController::class, 'storeStep2']);
    Route::post('/vas-requests/{vasRequest}/submit', [VasRequestController::class, 'submit']);

    // Lead submissions (specific routes before {lead})
    Route::get('/lead-submissions', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'index']);
    Route::get('/lead-submissions/filters', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'filters']);
    Route::get('/lead-submissions/columns', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'columns']);
    Route::post('/lead-submissions/columns', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'saveColumns']);
    Route::get('/lead-submissions/back-office-options', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'backOfficeOptions']);
    Route::get('/lead-submissions/audit-log', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'auditLog']);
    Route::post('/lead-submissions/bulk-assign', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'bulkAssign']);
    Route::patch('/lead-submissions/{lead}/status', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'updateStatus'])
        ->whereNumber('lead');
    Route::patch('/lead-submissions/{lead}/status-changed-at', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'updateStatusChangedAt'])
        ->whereNumber('lead');
    Route::put('/lead-submissions/{lead}/back-office', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'updateBackOffice'])
        ->whereNumber('lead');
    Route::get('/lead-submissions/{lead}/audits', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'audits'])->whereNumber('lead');
    Route::get('/lead-submissions/{lead}/documents/bulk-download', [LeadSubmissionController::class, 'bulkDownloadDocuments'])
        ->whereNumber('lead');
    Route::get('/lead-submissions/{lead}/documents/{document}/download', [LeadSubmissionController::class, 'downloadDocument'])
        ->whereNumber('lead')->whereNumber('document');
    Route::delete('/lead-submissions/{lead}/documents/{document}', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'deleteDocument'])
        ->whereNumber('lead')->whereNumber('document');
    Route::post('/lead-submissions/{lead}/documents', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'uploadDocuments'])
        ->whereNumber('lead');
    Route::get('/lead-submissions/current-draft', [LeadSubmissionController::class, 'currentDraft']);
    Route::get('/lead-submissions/categories', [LeadSubmissionController::class, 'categories']);
    Route::get('/lead-submissions/service-types', [LeadSubmissionController::class, 'serviceTypes']);
    Route::get('/lead-submissions/type-schema/{type}', [LeadSubmissionController::class, 'typeSchema']);
    Route::post('/lead-submissions/step-1', [LeadSubmissionController::class, 'storeStep1']);
    Route::get('/lead-submissions/{lead}/resubmission-data', [LeadSubmissionController::class, 'resubmissionData'])->whereNumber('lead');
    Route::post('/lead-submissions/{lead}/resubmit', [LeadSubmissionController::class, 'resubmit'])->whereNumber('lead');
    Route::get('/lead-submissions/{lead}', [LeadSubmissionController::class, 'show'])->whereNumber('lead');
    Route::put('/lead-submissions/{lead}/step-1', [LeadSubmissionController::class, 'updateStep1']);
    Route::delete('/lead-submissions/{lead}/discard', [LeadSubmissionController::class, 'discardDraft']);
    Route::post('/lead-submissions/{lead}/step-2', [LeadSubmissionController::class, 'storeStep2']);
    Route::post('/lead-submissions/{lead}/step-3', [LeadSubmissionController::class, 'storeStep4']); // Vue step 3 = documents
    Route::post('/lead-submissions/{lead}/step-4', [LeadSubmissionController::class, 'storeStep4']); // backward compatibility
    Route::post('/lead-submissions/{lead}/submit', [LeadSubmissionController::class, 'submit']);

    // Email follow-ups (SPA: add + listing on same page)
    Route::get('/email-follow-ups', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'index']);
    Route::get('/email-follow-ups/filters', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'filters']);
    Route::get('/email-follow-ups/columns', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'columns']);
    Route::post('/email-follow-ups/columns', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'saveColumns']);
    Route::post('/email-follow-ups', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'store']);
    Route::patch('/email-follow-ups/{emailFollowUp}', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'patch'])->whereNumber('emailFollowUp');
    Route::patch('/email-follow-ups/{emailFollowUp}/status', [\App\Http\Controllers\Api\EmailFollowUpController::class, 'updateStatus'])->whereNumber('emailFollowUp');

    // Column preferences
    Route::get('/modules/{module}/columns', [ColumnPreferenceController::class, 'show']);
    Route::post('/modules/{module}/columns', [ColumnPreferenceController::class, 'store']);

    // Employees (listing with columns/filters – uses User model)
    Route::get('/employees', [\App\Http\Controllers\Api\EmployeeApiController::class, 'index']);
    Route::get('/employees/filters', [\App\Http\Controllers\Api\EmployeeApiController::class, 'filters']);
    Route::get('/employees/columns', [\App\Http\Controllers\Api\EmployeeApiController::class, 'columns']);
    Route::post('/employees/columns', [\App\Http\Controllers\Api\EmployeeApiController::class, 'saveColumns']);
    Route::post('/employees/bulk-import', [\App\Http\Controllers\Api\EmployeeApiController::class, 'bulkImport']);

    // Cisco Extensions
    Route::get('/cisco-extensions', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'index']);
    Route::get('/cisco-extensions/summary', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'summary']);
    Route::get('/cisco-extensions/filters', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'filters']);
    Route::get('/cisco-extensions/columns', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'columns']);
    Route::post('/cisco-extensions/columns', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'saveColumns']);
    Route::get('/cisco-extensions/assignable-employees', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'assignableEmployees']);
    Route::get('/cisco-extensions/{ciscoExtension}', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'show'])->whereNumber('ciscoExtension');
    Route::get('/cisco-extensions/{ciscoExtension}/audit-log', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'auditLog'])->whereNumber('ciscoExtension');
    Route::post('/cisco-extensions', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'store']);
    Route::post('/cisco-extensions/bulk-import', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'bulkImport']);
    Route::put('/cisco-extensions/{ciscoExtension}', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'update'])->whereNumber('ciscoExtension');
    Route::patch('/cisco-extensions/{ciscoExtension}', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'patch'])->whereNumber('ciscoExtension');
    Route::delete('/cisco-extensions/{ciscoExtension}', [\App\Http\Controllers\Api\ExtensionsApiController::class, 'destroy'])->whereNumber('ciscoExtension');

    // Attendance log (login logs for SPA)
    Route::get('/attendance-log', [\App\Http\Controllers\Api\AttendanceLogApiController::class, 'index']);
    Route::get('/attendance-log/summary', [\App\Http\Controllers\Api\AttendanceLogApiController::class, 'summary']);
    Route::get('/attendance-log/filters', [\App\Http\Controllers\Api\AttendanceLogApiController::class, 'filters']);
    Route::post('/attendance-log/force-logout/log/{userLoginLog}', [\App\Http\Controllers\Api\AttendanceLogApiController::class, 'forceLogoutLog'])->whereNumber('userLoginLog');
    Route::post('/attendance-log/force-logout/user/{user}', [\App\Http\Controllers\Api\AttendanceLogApiController::class, 'forceLogoutUser'])->whereNumber('user');

    // Expenses (Expense Tracker)
    Route::get('/expenses', [\App\Http\Controllers\Api\ExpenseApiController::class, 'index']);
    Route::post('/expenses', [\App\Http\Controllers\Api\ExpenseApiController::class, 'store']);
    Route::get('/expenses/summary', [\App\Http\Controllers\Api\ExpenseApiController::class, 'summary']);
    Route::get('/expenses/filters', [\App\Http\Controllers\Api\ExpenseApiController::class, 'filters']);
    Route::get('/expenses/columns', [\App\Http\Controllers\Api\ExpenseApiController::class, 'columns']);
    Route::post('/expenses/columns', [\App\Http\Controllers\Api\ExpenseApiController::class, 'saveColumns']);
    Route::get('/expenses/{expense}', [\App\Http\Controllers\Api\ExpenseApiController::class, 'show'])->whereNumber('expense');
    Route::get('/expenses/{expense}/attachments/{attachment}/download', [\App\Http\Controllers\Api\ExpenseApiController::class, 'downloadAttachment'])->whereNumber(['expense', 'attachment'])->name('api.expenses.attachments.download');
    Route::delete('/expenses/{expense}/attachments/{attachment}', [\App\Http\Controllers\Api\ExpenseApiController::class, 'destroyAttachment'])->whereNumber(['expense', 'attachment']);
    Route::post('/expenses/{expense}/attachments', [\App\Http\Controllers\Api\ExpenseApiController::class, 'addAttachments'])->whereNumber('expense');
    Route::get('/expenses/{expense}/audit-log', [\App\Http\Controllers\Api\ExpenseApiController::class, 'auditLog'])->whereNumber('expense');
    Route::put('/expenses/{expense}', [\App\Http\Controllers\Api\ExpenseApiController::class, 'update'])->whereNumber('expense');
    Route::delete('/expenses/{expense}', [\App\Http\Controllers\Api\ExpenseApiController::class, 'destroy'])->whereNumber('expense');

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

    Route::get('/permissions/structure', [RoleApiController::class, 'permissionsStructure']);
    Route::get('/roles/{role}/permissions-page', [RoleApiController::class, 'permissionsPageData']);
    Route::get('/roles/{role}/permissions', [RoleApiController::class, 'rolePermissions']);
    Route::put('/roles/{role}/permissions', [RoleApiController::class, 'updateRolePermissions']);
    Route::apiResource('roles', RoleApiController::class);
});
