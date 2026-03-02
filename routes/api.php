<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ReportsApiController;
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
Route::get('/password-policy', [\App\Http\Controllers\Api\ChangePasswordController::class, 'policy']);

// ----- Auth (guest) – login needs session for same-origin SPA -----
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('web');

// ----- Protected (auth:sanctum = session OR token) – web middleware for session auth -----
Route::middleware(['web', 'auth:sanctum', 'verified', 'approved', '2fa_or_superadmin'])->group(function () {

    Route::get('/me', MeController::class);
    Route::get('/bootstrap', \App\Http\Controllers\Api\BootstrapController::class);

    // Change password (enforced by policy or user-initiated)
    Route::get('/change-password/policy', [\App\Http\Controllers\Api\ChangePasswordController::class, 'policy']);
    Route::post('/change-password', [\App\Http\Controllers\Api\ChangePasswordController::class, 'update']);

    // Dashboard
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);

    // Session heartbeat (extend session)
    Route::post('/session/heartbeat', function (\Illuminate\Http\Request $request) {
        // Touch the session to extend its lifetime
        $request->session()->regenerateToken();
        $timeoutMin = \App\Models\SecuritySetting::current()->auto_logout_after_minutes ?? 30;
        return response()->json([
            'message'    => 'Session extended.',
            'expires_at' => now()->addMinutes($timeoutMin)->toIso8601String(),
        ]);
    });

    // Table page-size preferences (per user + module)
    Route::get('/table-preferences/{module}', [\App\Http\Controllers\Api\TablePreferenceController::class, 'show']);
    Route::post('/table-preferences/{module}', [\App\Http\Controllers\Api\TablePreferenceController::class, 'store']);

    // Form drafts (auto-save)
    Route::get('/form-drafts/{module}/{recordRef}', [\App\Http\Controllers\Api\FormDraftController::class, 'show']);
    Route::post('/form-drafts/{module}/{recordRef}', [\App\Http\Controllers\Api\FormDraftController::class, 'store']);
    Route::delete('/form-drafts/{module}/{recordRef}', [\App\Http\Controllers\Api\FormDraftController::class, 'destroy']);

    // Field submissions
    Route::get('/field-submissions/team-options', [FieldSubmissionController::class, 'teamOptions']);
    Route::get('/field-submissions/field-agent-options', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'fieldAgentOptions']);
    Route::post('/field-submissions/bulk-assign', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'bulkAssign']);
    Route::get('/field-submissions/bulk-assign/{trackingId}/status', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'bulkAssignStatus']);
    Route::post('/field-submissions', [FieldSubmissionController::class, 'store']);
    Route::get('/field-submissions', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'index'])->middleware('api.cache:10,20');
    Route::get('/field-submissions/filters', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'filters'])->middleware('api.cache:60,120');
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
    Route::get('/field-submissions/{fieldSubmission}/audits', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'audits'])->whereNumber('fieldSubmission');
    Route::get('/field-submissions/audit-log', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'auditLog']);

    // Special requests
    Route::post('/special-requests', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'store']);
    Route::get('/special-requests', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'index'])->middleware('api.cache:10,20');
    Route::get('/special-requests/filters', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'filters'])->middleware('api.cache:60,120');
    Route::get('/special-requests/columns', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'columns']);
    Route::post('/special-requests/columns', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'saveColumns']);
    Route::get('/special-requests/bootstrap', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'bootstrap'])->middleware('api.cache:15,30');
    Route::get('/special-requests/{specialRequest}', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'show'])->whereNumber('specialRequest');
    Route::put('/special-requests/{specialRequest}', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'update'])->whereNumber('specialRequest');
    Route::patch('/special-requests/{specialRequest}', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'patch'])->whereNumber('specialRequest');
    Route::get('/special-requests/{specialRequest}/audits', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'audits'])->whereNumber('specialRequest');
    Route::post('/special-requests/{specialRequest}/documents', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'uploadDocuments'])->whereNumber('specialRequest');
    Route::delete('/special-requests/{specialRequest}/documents/{document}', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'deleteDocument'])->whereNumber('specialRequest');
    Route::get('/special-requests/{specialRequest}/documents/{document}/download', [\App\Http\Controllers\Api\SpecialRequestApiController::class, 'downloadDocument'])->whereNumber('specialRequest');

    // Customer support
    Route::get('/customer-support/team-options', [CustomerSupportController::class, 'teamOptions']);
    Route::get('/customer-support/csr-options', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'csrOptions']);
    Route::get('/customer-support/csrs-by-account', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'csrsByAccount']);
    Route::post('/customer-support/bulk-assign', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'bulkAssign']);
    Route::get('/customer-support/bulk-assign/{trackingId}/status', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'bulkAssignStatus']);
    Route::post('/customer-support', [CustomerSupportController::class, 'store']);
    Route::get('/customer-support', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'index'])->middleware('api.cache:10,20');
    Route::get('/customer-support/filters', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'filters'])->middleware('api.cache:60,120');
    Route::get('/customer-support/columns', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'columns']);
    Route::get('/customer-support/edit-options', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'editOptions']);
    Route::post('/customer-support/columns', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'saveColumns']);
    Route::post('/customer-support/{customerSupportSubmission}/resubmit', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'resubmit'])
        ->whereNumber('customerSupportSubmission');
    Route::get('/customer-support/{customerSupportSubmission}', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'show'])
        ->whereNumber('customerSupportSubmission');
    Route::get('/customer-support/{customerSupportSubmission}/audits', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'audits'])
        ->whereNumber('customerSupportSubmission');
    Route::post('/customer-support/{customerSupportSubmission}/attachments', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'addAttachments'])
        ->whereNumber('customerSupportSubmission');
    Route::get('/customer-support/{customerSupportSubmission}/attachments/{index}/download', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'downloadAttachment'])
        ->whereNumber(['customerSupportSubmission', 'index']);
    Route::patch('/customer-support/{customerSupportSubmission}/assign-csr', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'assignCsr'])
        ->whereNumber('customerSupportSubmission');
    Route::patch('/customer-support/{customerSupportSubmission}', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'patch'])
        ->whereNumber('customerSupportSubmission');

    // Clients
    Route::get('/clients', [\App\Http\Controllers\Api\ClientApiController::class, 'index'])->middleware('etag.response');
    Route::post('/clients', [\App\Http\Controllers\Api\ClientApiController::class, 'store']);
    Route::get('/clients/filters', [\App\Http\Controllers\Api\ClientApiController::class, 'filters'])->middleware('etag.response');
    Route::get('/clients+filters', [\App\Http\Controllers\Api\ClientApiController::class, 'clientsAndFilters'])->middleware('etag.response');
    Route::get('/clients/columns', [\App\Http\Controllers\Api\ClientApiController::class, 'columns']);
    Route::post('/clients/columns', [\App\Http\Controllers\Api\ClientApiController::class, 'saveColumns']);
    Route::post('/clients/import', [\App\Http\Controllers\Api\ClientApiController::class, 'importCsv']);
    Route::post('/clients/{client}/products', [\App\Http\Controllers\Api\ClientApiController::class, 'storeProduct'])->whereNumber('client');
    Route::get('/clients/{client}/products', [\App\Http\Controllers\Api\ClientApiController::class, 'products'])->whereNumber('client');
    Route::get('/clients/{client}/vas-requests', [\App\Http\Controllers\Api\ClientApiController::class, 'vasRequests'])->whereNumber('client');
    Route::get('/clients/{client}/customer-support', [\App\Http\Controllers\Api\ClientApiController::class, 'customerSupport'])->whereNumber('client');
    Route::get('/clients/{client}/audits', [\App\Http\Controllers\Api\ClientApiController::class, 'audits'])->whereNumber('client');
    Route::put('/clients/{client}', [\App\Http\Controllers\Api\ClientApiController::class, 'update'])->whereNumber('client');
    Route::put('/clients/{client}/inline', [\App\Http\Controllers\Api\ClientApiController::class, 'inlineUpdate'])->whereNumber('client');
    Route::put('/clients/{client}/company-details', [\App\Http\Controllers\Api\ClientApiController::class, 'updateCompanyDetails'])->whereNumber('client');
    Route::put('/clients/{client}/contacts', [\App\Http\Controllers\Api\ClientApiController::class, 'updateContacts'])->whereNumber('client');
    Route::put('/clients/{client}/addresses', [\App\Http\Controllers\Api\ClientApiController::class, 'updateAddresses'])->whereNumber('client');
    Route::post('/clients/renewal-alerts/generate', [\App\Http\Controllers\Api\ClientApiController::class, 'generateRenewalAlerts']);
    Route::get('/clients/{client}/alerts', [\App\Http\Controllers\Api\ClientApiController::class, 'alerts'])->whereNumber('client');
    Route::post('/clients/{client}/alerts', [\App\Http\Controllers\Api\ClientApiController::class, 'storeAlert'])->whereNumber('client');
    Route::put('/clients/{client}/alerts/{alert}', [\App\Http\Controllers\Api\ClientApiController::class, 'updateAlert'])->whereNumber('client')->whereNumber('alert');
    Route::post('/clients/{client}/alerts/{alert}/resolve', [\App\Http\Controllers\Api\ClientApiController::class, 'resolveAlert'])->whereNumber('client')->whereNumber('alert');
    Route::get('/clients/{client}', [\App\Http\Controllers\Api\ClientApiController::class, 'show'])->whereNumber('client');

    // VAS requests
    Route::get('/vas-requests/team-options', [VasRequestController::class, 'teamOptions']);
    Route::get('/vas-requests/document-schema', [VasRequestController::class, 'documentSchemaResponse']);
    Route::post('/vas-requests/step-1', [VasRequestController::class, 'storeStep1']);
    Route::get('/vas-requests', [\App\Http\Controllers\Api\VasRequestApiController::class, 'index'])->middleware('api.cache:10,20');
    Route::get('/vas-requests/filters', [\App\Http\Controllers\Api\VasRequestApiController::class, 'filters'])->middleware('api.cache:60,120');
    Route::get('/vas-requests/columns', [\App\Http\Controllers\Api\VasRequestApiController::class, 'columns']);
    Route::post('/vas-requests/columns', [\App\Http\Controllers\Api\VasRequestApiController::class, 'saveColumns']);
    Route::get('/vas-requests/back-office-options', [\App\Http\Controllers\Api\VasRequestApiController::class, 'backOfficeOptions']);
    Route::post('/vas-requests/bulk-assign', [\App\Http\Controllers\Api\VasRequestApiController::class, 'bulkAssign']);
    Route::get('/vas-requests/bulk-assign/{trackingId}/status', [\App\Http\Controllers\Api\VasRequestApiController::class, 'bulkAssignStatus']);
    Route::get('/vas-requests/{vasRequest}/audits', [\App\Http\Controllers\Api\VasRequestApiController::class, 'audits'])->whereNumber('vasRequest');
    Route::get('/vas-requests/{vasRequest}', [VasRequestController::class, 'show'])->whereNumber('vasRequest');
    Route::get('/vas-requests/{vasRequest}/documents/{document}/download', [VasRequestController::class, 'downloadDocument'])->whereNumber('vasRequest')->whereNumber('document');
    Route::delete('/vas-requests/{vasRequest}/documents/{document}', [VasRequestController::class, 'deleteDocument'])->whereNumber('vasRequest')->whereNumber('document');
    Route::put('/vas-requests/{vasRequest}', [VasRequestController::class, 'update'])->whereNumber('vasRequest');
    Route::patch('/vas-requests/{vasRequest}', [\App\Http\Controllers\Api\VasRequestApiController::class, 'patch'])->whereNumber('vasRequest');
    Route::post('/vas-requests/{vasRequest}/step-2', [VasRequestController::class, 'storeStep2']);
    Route::post('/vas-requests/{vasRequest}/submit', [VasRequestController::class, 'submit']);
    Route::post('/vas-requests/{vasRequest}/resubmit', [VasRequestController::class, 'resubmit'])->whereNumber('vasRequest');

    // Lead submissions (specific routes before {lead})
    Route::get('/lead-submissions/team-options', [FieldSubmissionController::class, 'teamOptions']);
    Route::get('/lead-submissions', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'index'])->middleware('api.cache:10,20');
    Route::get('/lead-submissions/filters', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'filters'])->middleware('api.cache:60,120');
    Route::get('/lead-submissions/columns', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'columns']);
    Route::post('/lead-submissions/columns', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'saveColumns']);
    Route::get('/lead-submissions/back-office-options', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'backOfficeOptions']);
    Route::get('/lead-submissions/audit-log', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'auditLog']);
    Route::post('/lead-submissions/bulk-assign', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'bulkAssign']);
    Route::get('/lead-submissions/bulk-assign/{trackingId}/status', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'bulkAssignStatus']);
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

    // Personal notes
    Route::get('/personal-notes', [\App\Http\Controllers\Api\PersonalNoteApiController::class, 'index']);
    Route::post('/personal-notes', [\App\Http\Controllers\Api\PersonalNoteApiController::class, 'store']);
    Route::get('/personal-notes/{personal_note}', [\App\Http\Controllers\Api\PersonalNoteApiController::class, 'show'])->whereNumber('personal_note');
    Route::put('/personal-notes/{personal_note}', [\App\Http\Controllers\Api\PersonalNoteApiController::class, 'update'])->whereNumber('personal_note');
    Route::delete('/personal-notes/{personal_note}', [\App\Http\Controllers\Api\PersonalNoteApiController::class, 'destroy'])->whereNumber('personal_note');

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

    // DSP Tracker (CSV import stored in DB; delete last batch)
    Route::get('/dsp-tracker', [\App\Http\Controllers\Api\DspTrackerApiController::class, 'index']);
    Route::post('/dsp-tracker/import', [\App\Http\Controllers\Api\DspTrackerApiController::class, 'import']);
    Route::delete('/dsp-tracker/batch/{batchId}', [\App\Http\Controllers\Api\DspTrackerApiController::class, 'destroyBatch'])->where('batchId', '[a-f0-9\-]{36}');

    // Verifiers Detail (directory for DSP Tracker: add, import/export CSV, inline edit, delete with permission)
    Route::get('/verifiers/export-csv', [\App\Http\Controllers\Api\VerifierApiController::class, 'exportCsv']);
    Route::post('/verifiers/import-csv', [\App\Http\Controllers\Api\VerifierApiController::class, 'importCsv']);
    Route::get('/verifiers', [\App\Http\Controllers\Api\VerifierApiController::class, 'index']);
    Route::post('/verifiers', [\App\Http\Controllers\Api\VerifierApiController::class, 'store']);
    Route::put('/verifiers/{verifier}', [\App\Http\Controllers\Api\VerifierApiController::class, 'update'])->whereNumber('verifier');
    Route::delete('/verifiers/{verifier}', [\App\Http\Controllers\Api\VerifierApiController::class, 'destroy'])->whereNumber('verifier');

    // Teams
    Route::get('/teams', [\App\Http\Controllers\Api\TeamController::class, 'index']);
    Route::get('/teams/filters', [\App\Http\Controllers\Api\TeamController::class, 'filters']);
    Route::get('/teams/columns', [\App\Http\Controllers\Api\TeamController::class, 'columns']);
    Route::post('/teams/columns', [\App\Http\Controllers\Api\TeamController::class, 'saveColumns']);
    Route::get('/teams/available-members', [\App\Http\Controllers\Api\TeamController::class, 'availableMembers']);
    Route::post('/teams/bulk-delete', [\App\Http\Controllers\Api\TeamController::class, 'bulkDelete']);
    Route::post('/teams/bulk-status', [\App\Http\Controllers\Api\TeamController::class, 'bulkStatusChange']);
    Route::post('/teams', [\App\Http\Controllers\Api\TeamController::class, 'store']);
    Route::get('/teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'show'])->whereNumber('team');
    Route::put('/teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'update'])->whereNumber('team');
    Route::delete('/teams/{team}', [\App\Http\Controllers\Api\TeamController::class, 'destroy'])->whereNumber('team');
    Route::get('/teams/{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'members'])->whereNumber('team');
    Route::post('/teams/{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'addMembers'])->whereNumber('team');
    Route::delete('/teams/{team}/members/{member}', [\App\Http\Controllers\Api\TeamController::class, 'removeMember'])->whereNumber(['team', 'member']);

    // Users (list, show, update, delete, create – super admin / authorized)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/filters', [UserController::class, 'filters']);
    Route::get('/users/columns', [UserController::class, 'columns']);
    Route::post('/users/columns', [UserController::class, 'saveColumns']);
    Route::post('/users', [UserController::class, 'store']);
    Route::post('/users/bulk-activate', [UserController::class, 'bulkActivate']);
    Route::post('/users/bulk-deactivate', [UserController::class, 'bulkDeactivate']);
    Route::get('/users/{user}', [UserController::class, 'show'])->whereNumber('user');
    Route::get('/users/{user}/prime', [UserController::class, 'prime'])->whereNumber('user');
    Route::get('/users/{user}/extras', [UserController::class, 'extras'])->whereNumber('user');
    Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user');
    Route::patch('/users/{user}', [UserController::class, 'patch'])->whereNumber('user');
    Route::get('/users/{user}/audit-log', [UserController::class, 'auditLog'])->whereNumber('user');
    Route::post('/users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])->whereNumber('user');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->whereNumber('user');

    // Super admin only (same auth stack as above; extra role check)
    Route::middleware(['role:superadmin'])->prefix('super-admin')->group(function () {
        Route::get('/team-role-mappings', [TeamRoleMappingController::class, 'index']);
        Route::put('/team-role-mappings', [TeamRoleMappingController::class, 'update']);

        Route::get('/permissions/structure', [RoleApiController::class, 'permissionsStructure']);
        Route::get('/roles/{role}/permissions-page', [RoleApiController::class, 'permissionsPageData']);
        Route::get('/roles/{role}/permissions', [RoleApiController::class, 'rolePermissions']);
        Route::put('/roles/{role}/permissions', [RoleApiController::class, 'updateRolePermissions']);
        Route::post('/roles/{role}/parents', [RoleApiController::class, 'addParentRole']);
        Route::delete('/roles/{role}/parents/{parentRole}', [RoleApiController::class, 'removeParentRole']);
        Route::apiResource('roles', RoleApiController::class);
    });

    Route::get('/meta/timezones', [\App\Http\Controllers\Api\MetaController::class, 'timezones']);
    Route::get('/meta/landing-pages', [\App\Http\Controllers\Api\MetaController::class, 'landingPages']);
    // Settings APIs are superadmin-only.
    Route::middleware(['role:superadmin'])->group(function () {
        // System Preferences
        Route::get('/system-preferences', [\App\Http\Controllers\Api\SystemPreferenceController::class, 'index']);
        Route::put('/system-preferences', [\App\Http\Controllers\Api\SystemPreferenceController::class, 'update']);
        Route::post('/system-preferences/reset', [\App\Http\Controllers\Api\SystemPreferenceController::class, 'reset']);

        // SLA Rules
        Route::get('/sla-rules', [\App\Http\Controllers\Api\SlaRuleController::class, 'index']);
        Route::patch('/sla-rules/{slaRule}', [\App\Http\Controllers\Api\SlaRuleController::class, 'update']);
        Route::patch('/sla-rules/{slaRule}/toggle', [\App\Http\Controllers\Api\SlaRuleController::class, 'toggle']);

        // Notification & Email Rules
        Route::get('/notification-config', [\App\Http\Controllers\Api\NotificationConfigController::class, 'index']);
        Route::put('/notification-settings', [\App\Http\Controllers\Api\NotificationConfigController::class, 'updateSettings']);
        Route::patch('/notification-triggers/{trigger}', [\App\Http\Controllers\Api\NotificationConfigController::class, 'updateTrigger']);
        Route::put('/notification-triggers/{trigger}/{channel}', [\App\Http\Controllers\Api\NotificationConfigController::class, 'updateUserTriggerPreference']);
        Route::post('/notification-triggers/{channel}/reset', [\App\Http\Controllers\Api\NotificationConfigController::class, 'resetChannelPreferences']);
        Route::put('/notification-escalations', [\App\Http\Controllers\Api\NotificationConfigController::class, 'upsertEscalations']);
        Route::post('/notification-test', [\App\Http\Controllers\Api\NotificationConfigController::class, 'testNotification']);
        Route::get('/notification-logs', [\App\Http\Controllers\Api\NotificationLogController::class, 'index']);
        Route::delete('/notification-logs/{notificationLog}', [\App\Http\Controllers\Api\NotificationLogController::class, 'destroy']);

        // Escalation levels + email templates
        Route::get('/escalation-levels', [\App\Http\Controllers\Api\EscalationLevelController::class, 'index']);
        Route::post('/escalation-levels', [\App\Http\Controllers\Api\EscalationLevelController::class, 'store']);
        Route::put('/escalation-levels/reorder', [\App\Http\Controllers\Api\EscalationLevelController::class, 'reorder']);
        Route::post('/escalation-levels/reset', [\App\Http\Controllers\Api\EscalationLevelController::class, 'reset']);
        Route::put('/escalation-levels/{escalationLevel}', [\App\Http\Controllers\Api\EscalationLevelController::class, 'update']);
        Route::delete('/escalation-levels/{escalationLevel}', [\App\Http\Controllers\Api\EscalationLevelController::class, 'destroy']);
        Route::get('/email-templates', [\App\Http\Controllers\Api\EmailTemplateController::class, 'index']);
        Route::post('/email-templates', [\App\Http\Controllers\Api\EmailTemplateController::class, 'store']);
        Route::get('/email-templates/{emailTemplate}', [\App\Http\Controllers\Api\EmailTemplateController::class, 'show']);
        Route::put('/email-templates/{emailTemplate}', [\App\Http\Controllers\Api\EmailTemplateController::class, 'update']);
        Route::delete('/email-templates/{emailTemplate}', [\App\Http\Controllers\Api\EmailTemplateController::class, 'destroy']);

        // Announcement Center
        Route::get('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
        Route::post('/announcements', [\App\Http\Controllers\Api\AnnouncementController::class, 'store']);
        Route::get('/announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'show']);
        Route::put('/announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'update']);
        Route::patch('/announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'patchField']);
        Route::patch('/announcements/{announcement}/publish-now', [\App\Http\Controllers\Api\AnnouncementController::class, 'publishNow']);
        Route::post('/announcements/{announcement}/duplicate', [\App\Http\Controllers\Api\AnnouncementController::class, 'duplicate']);
        Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Api\AnnouncementController::class, 'destroy']);
        Route::delete('/announcements/{announcement}/permanent', [\App\Http\Controllers\Api\AnnouncementController::class, 'forceDelete']);
        Route::post('/announcements/{announcement}/acknowledge', [\App\Http\Controllers\Api\AnnouncementController::class, 'acknowledge']);

        // Settings dashboard status
        Route::get('/settings/status', \App\Http\Controllers\Api\SettingsStatusController::class);

        // Security, Session & Access Control
        Route::get('/security-settings', [\App\Http\Controllers\Api\SecuritySettingsController::class, 'index']);
        Route::put('/security-settings', [\App\Http\Controllers\Api\SecuritySettingsController::class, 'update']);
        Route::post('/security-settings/reset', [\App\Http\Controllers\Api\SecuritySettingsController::class, 'reset']);

        // Audit Logs
        Route::get('/audit-logs/stats', [\App\Http\Controllers\Api\AuditLogController::class, 'stats']);
        Route::get('/audit-logs/meta', [\App\Http\Controllers\Api\AuditLogController::class, 'meta']);
        Route::get('/audit-logs/export', [\App\Http\Controllers\Api\AuditLogController::class, 'export']);
        Route::get('/audit-logs/{auditLog}', [\App\Http\Controllers\Api\AuditLogController::class, 'show']);
        Route::get('/audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);

        // Library — Templates & Forms
        Route::get('/library/documents/meta', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'meta']);
        Route::get('/library/export', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'export']);
        Route::get('/library/documents/{document}/download', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'download']);
        Route::get('/library/documents/{document}/versions', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'versions']);
        Route::patch('/library/documents/{document}/toggle', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'toggle']);
        Route::get('/library/documents/{document}', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'show']);
        Route::post('/library/documents/bulk-upload', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'bulkUpload']);
        Route::post('/library/documents', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'store']);
        Route::post('/library/documents/{document}', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'update']);
        Route::delete('/library/documents/{document}', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'destroy']);
        Route::get('/library/documents', [\App\Http\Controllers\Api\LibraryDocumentController::class, 'index']);
    });

    // Aggregated bootstrap endpoints (reduce 3-4 requests to 1 per page)
    Route::get('/lead-submissions/bootstrap', [\App\Http\Controllers\Api\LeadSubmissionApiController::class, 'bootstrap'])->middleware('api.cache:15,30');
    Route::get('/field-submissions/bootstrap', [\App\Http\Controllers\Api\FieldSubmissionApiController::class, 'bootstrap'])->middleware('api.cache:15,30');
    Route::get('/customer-support/bootstrap', [\App\Http\Controllers\Api\CustomerSupportApiController::class, 'bootstrap'])->middleware('api.cache:15,30');
    Route::get('/vas-requests/bootstrap', [\App\Http\Controllers\Api\VasRequestApiController::class, 'bootstrap'])->middleware('api.cache:15,30');

    // Reports (stats for Lead and Field Operations report pages)
    Route::get('/reports/lead-stats', [ReportsApiController::class, 'leadStats'])->middleware('api.cache:15,30');
    Route::get('/reports/field-stats', [ReportsApiController::class, 'fieldStats'])->middleware('api.cache:15,30');
    Route::get('/reports/vas-stats', [ReportsApiController::class, 'vasStats'])->middleware('api.cache:15,30');
    Route::get('/reports/sla-performance', [ReportsApiController::class, 'slaPerformance'])->middleware('api.cache:30,60');

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
