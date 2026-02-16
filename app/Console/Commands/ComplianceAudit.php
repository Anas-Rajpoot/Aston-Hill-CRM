<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SystemAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ComplianceAudit extends Command
{
    protected $signature = 'compliance:audit {--fix : Auto-fix missing permissions and assign to superadmin} {--json : Output JSON only}';

    protected $description = 'Run a full CRM compliance audit: routes, permissions, audit tables, datatable columns, and button consistency';

    private array $report = [
        'generated_at' => '',
        'modules_tested' => [],
        'permissions' => ['total' => 0, 'missing' => [], 'unassigned' => []],
        'routes' => ['total' => 0, 'unguarded' => [], 'protected' => 0],
        'audit_tables' => ['pass' => [], 'fail' => []],
        'datatable_features' => [],
        'warnings' => [],
    ];

    public function handle(): int
    {
        $this->report['generated_at'] = now()->toIso8601String();

        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║        CRM COMPLIANCE AUDIT SCANNER              ║');
        $this->info('╚═══════════════════════════════════════════════════╝');
        $this->newLine();

        $this->scanRoutes();
        $this->scanPermissions();
        $this->scanAuditTables();
        $this->scanDataTableComponents();
        $this->scanControllerAuditCoverage();

        if ($this->option('fix')) {
            $this->autoFixPermissions();
        }

        $this->generateReports();

        return self::SUCCESS;
    }

    // ─── STEP 1: Route Scanning ──────────────────────────────────────────

    private function scanRoutes(): void
    {
        $this->info('📌 STEP 1: Scanning all registered routes...');

        $routes = collect(Route::getRoutes()->getRoutes());
        $apiRoutes = $routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/'));

        $this->report['routes']['total'] = $apiRoutes->count();

        $moduleMap = [];
        foreach ($apiRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            $middleware = $route->middleware();
            $action = $route->getActionName();

            $module = $this->resolveModule($uri);

            if (! isset($moduleMap[$module])) {
                $moduleMap[$module] = ['routes' => [], 'controller' => null];
            }

            $isProtected = ! empty(array_intersect($middleware, [
                'auth:sanctum', 'role:superadmin', 'permission', 'crud_permission', 'can',
            ]));

            $routeInfo = [
                'methods'    => implode('|', array_diff($methods, ['HEAD'])),
                'uri'        => $uri,
                'action'     => $action,
                'middleware'  => $middleware,
                'protected'  => $isProtected,
            ];

            $moduleMap[$module]['routes'][] = $routeInfo;

            if (str_contains($action, 'Controller')) {
                $moduleMap[$module]['controller'] = explode('@', $action)[0] ?? $action;
            }

            if ($isProtected) {
                $this->report['routes']['protected']++;
            }
        }

        $this->report['modules_tested'] = array_keys($moduleMap);

        $this->info("   Found {$apiRoutes->count()} API routes across " . count($moduleMap) . ' modules');
        $this->newLine();
    }

    // ─── STEP 2: Permission Scanning ──────────────────────────────────────

    private function scanPermissions(): void
    {
        $this->info('📌 STEP 2: Scanning permissions inventory...');

        $configModules = config('permissions.modules', []);
        $configActions = array_keys(config('permissions.actions', []));
        $structure = config('permissions.structure', []);

        $expectedPermissions = [];

        // Module × Action matrix
        foreach ($configModules as $moduleKey => $label) {
            foreach ($configActions as $action) {
                $expectedPermissions[] = "{$moduleKey}.{$action}";
            }
        }

        // Structure-based
        foreach ($structure as $moduleKey => $module) {
            foreach ($module['permissions'] ?? [] as $perm) {
                $expectedPermissions[] = "{$moduleKey}.{$perm['key']}";
            }
        }

        // Standalone
        $standalone = [
            'manage-notification-rules', 'manage-escalation-levels',
            'manage-system-preferences', 'manage-sla', 'manage-security-settings',
            'manage-announcements', 'manage-library', 'view-library',
            'download-library', 'export-audit-logs',
        ];
        $expectedPermissions = array_merge($expectedPermissions, $standalone);
        $expectedPermissions = array_unique($expectedPermissions);

        $this->report['permissions']['total'] = count($expectedPermissions);

        $dbPermissions = Permission::where('guard_name', 'web')->pluck('name')->toArray();
        $missing = array_diff($expectedPermissions, $dbPermissions);

        $this->report['permissions']['missing'] = array_values($missing);

        // Check superadmin assignments
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadminPerms = $superadmin->permissions->pluck('name')->toArray();
            $unassigned = array_diff($dbPermissions, $superadminPerms);
            $this->report['permissions']['unassigned'] = array_values($unassigned);
        }

        $this->info("   Expected: " . count($expectedPermissions) . " | In DB: " . count($dbPermissions) . " | Missing: " . count($missing));

        if (count($missing) > 0) {
            $this->warn('   ⚠ Missing permissions: ' . implode(', ', array_slice($missing, 0, 10)) . (count($missing) > 10 ? '...' : ''));
        } else {
            $this->info('   ✅ All expected permissions exist in DB');
        }

        if (! empty($this->report['permissions']['unassigned'])) {
            $this->warn('   ⚠ Superadmin missing ' . count($this->report['permissions']['unassigned']) . ' permission assignments');
        } else {
            $this->info('   ✅ Superadmin has all permissions assigned');
        }

        $this->newLine();
    }

    // ─── STEP 3: Audit Tables Scanning ────────────────────────────────────

    private function scanAuditTables(): void
    {
        $this->info('📌 STEP 3: Scanning audit tables...');

        $auditTables = [
            'audit_logs' => ['user_id', 'user_name', 'user_role', 'action', 'module', 'ip', 'user_agent', 'old_values', 'new_values', 'occurred_at'],
            'system_audit_logs' => ['user_id', 'event', 'entity_type', 'entity_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
            'lead_submission_audits' => ['lead_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'field_submission_audits' => ['field_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'vas_request_audits' => ['vas_request_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'customer_support_submission_audits' => ['customer_support_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'client_audits' => ['client_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'expense_audits' => ['expense_id', 'user_id', 'column_name', 'old_value', 'new_value'],
            'user_audits' => ['user_id'],
            'cisco_extension_audits' => ['cisco_extension_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        ];

        foreach ($auditTables as $table => $requiredCols) {
            if (! Schema::hasTable($table)) {
                $this->report['audit_tables']['fail'][] = ['table' => $table, 'reason' => 'Table does not exist'];
                $this->error("   ❌ {$table} — table does not exist");
                continue;
            }

            $columns = Schema::getColumnListing($table);
            $missingCols = array_diff($requiredCols, $columns);

            if (empty($missingCols)) {
                $this->report['audit_tables']['pass'][] = $table;
                $this->info("   ✅ {$table} — all columns present");
            } else {
                $this->report['audit_tables']['fail'][] = [
                    'table' => $table,
                    'reason' => 'Missing columns: ' . implode(', ', $missingCols),
                ];
                $this->warn("   ⚠ {$table} — missing: " . implode(', ', $missingCols));
            }
        }

        // Check record counts
        $globalCount = AuditLog::count();
        $systemCount = SystemAuditLog::count();
        $this->info("   📊 audit_logs: {$globalCount} records | system_audit_logs: {$systemCount} records");

        $this->newLine();
    }

    // ─── STEP 4: DataTable Component Scanning ─────────────────────────────

    private function scanDataTableComponents(): void
    {
        $this->info('📌 STEP 4: Scanning DataTable components...');

        $tableComponents = [
            'LeadTable' => resource_path('js/components/lead-submissions/LeadTable.vue'),
            'FieldTable' => resource_path('js/components/field-submissions/FieldTable.vue'),
            'VasRequestTable' => resource_path('js/components/vas-requests/VasRequestTable.vue'),
            'CustomerSupportTable' => resource_path('js/components/customer-support/CustomerSupportTable.vue'),
            'ClientTable' => resource_path('js/components/clients/ClientTable.vue'),
            'EmployeeTable' => resource_path('js/components/employees/EmployeeTable.vue'),
            'ExtensionsTable' => resource_path('js/components/extensions/ExtensionsTable.vue'),
            'ExpenseTable' => resource_path('js/components/expenses/ExpenseTable.vue'),
            'OrderStatusTable' => resource_path('js/components/order-status/OrderStatusTable.vue'),
            'EmailFollowUpTable' => resource_path('js/components/email-followups/EmailFollowUpTable.vue'),
        ];

        $listingPages = [
            'AuditLogsPage' => resource_path('js/pages/settings/AuditLogsPage.vue'),
            'LibraryPage' => resource_path('js/pages/settings/LibraryPage.vue'),
            'AnnouncementCenterPage' => resource_path('js/pages/settings/AnnouncementCenterPage.vue'),
            'AttendanceLogPage' => resource_path('js/pages/attendance/AttendanceLogPage.vue'),
            'DSPTrackerListingPage' => resource_path('js/pages/dsp-tracker/DSPTrackerListingPage.vue'),
        ];

        $allFiles = array_merge($tableComponents, $listingPages);

        foreach ($allFiles as $name => $path) {
            if (! File::exists($path)) {
                $this->report['datatable_features'][] = [
                    'component' => $name,
                    'sort' => false,
                    'filter' => false,
                    'customize_columns' => false,
                    'issue' => 'File not found',
                ];
                $this->warn("   ⚠ {$name} — file not found: {$path}");
                continue;
            }

            $content = File::get($path);
            $hasSortable = str_contains($content, 'SORTABLE_COLUMNS') || str_contains($content, 'sortable');
            $hasFilter = str_contains($content, 'filters') || str_contains($content, 'FiltersBar');
            $hasColumnCustomizer = str_contains($content, 'Customize Columns') || str_contains($content, 'columnModal');
            $hasInlineEdit = str_contains($content, 'startEditing') || str_contains($content, 'inlineEdit') || str_contains($content, 'editingRow');

            // Count sortable vs total columns
            $sortableCount = 0;
            $totalColumns = 0;
            if (preg_match('/SORTABLE_COLUMNS\s*=\s*\[([^\]]+)\]/s', $content, $m)) {
                $sortableCount = substr_count($m[1], "'");
                $sortableCount = intdiv($sortableCount, 2);
            }
            if (preg_match('/COLUMN_LABELS\s*=\s*\{([^}]+)\}/s', $content, $m2)) {
                $totalColumns = substr_count($m2[1], ':');
            }
            if (preg_match('/ALL_COLUMNS\s*=\s*\[/s', $content, $m3)) {
                $totalColumns = $totalColumns ?: substr_count($content, "key:");
            }

            $issues = [];
            if (! $hasSortable) $issues[] = 'No sort functionality';
            if (! $hasColumnCustomizer && str_contains($name, 'Table')) $issues[] = 'No column customizer (table component — uses parent page)';

            $this->report['datatable_features'][] = [
                'component'         => $name,
                'sort'              => $hasSortable,
                'filter'            => $hasFilter,
                'customize_columns' => $hasColumnCustomizer,
                'inline_edit'       => $hasInlineEdit,
                'sortable_cols'     => $sortableCount,
                'total_cols'        => $totalColumns,
                'issues'            => implode('; ', $issues),
            ];

            $status = $hasSortable ? '✅' : '⚠';
            $sortInfo = $sortableCount > 0 ? " ({$sortableCount} sortable)" : '';
            $this->info("   {$status} {$name}: sort=" . ($hasSortable ? 'Y' : 'N')
                . " filter=" . ($hasFilter ? 'Y' : 'N')
                . " colCustomizer=" . ($hasColumnCustomizer ? 'Y' : 'N')
                . " inlineEdit=" . ($hasInlineEdit ? 'Y' : 'N')
                . $sortInfo);
        }

        $this->newLine();
    }

    // ─── STEP 5: Controller Audit Coverage ────────────────────────────────

    private function scanControllerAuditCoverage(): void
    {
        $this->info('📌 STEP 5: Scanning controller audit coverage...');

        $controllers = [
            'UserController' => app_path('Http/Controllers/Api/UserController.php'),
            'ClientApiController' => app_path('Http/Controllers/Api/ClientApiController.php'),
            'ExpenseApiController' => app_path('Http/Controllers/Api/ExpenseApiController.php'),
            'LeadSubmissionApiController' => app_path('Http/Controllers/Api/LeadSubmissionApiController.php'),
            'FieldSubmissionApiController' => app_path('Http/Controllers/Api/FieldSubmissionApiController.php'),
            'VasRequestApiController' => app_path('Http/Controllers/Api/VasRequestApiController.php'),
            'CustomerSupportApiController' => app_path('Http/Controllers/Api/CustomerSupportApiController.php'),
            'ExtensionsApiController' => app_path('Http/Controllers/Api/ExtensionsApiController.php'),
            'AnnouncementController' => app_path('Http/Controllers/Api/AnnouncementController.php'),
            'LibraryDocumentController' => app_path('Http/Controllers/Api/LibraryDocumentController.php'),
            'SecuritySettingsController' => app_path('Http/Controllers/Api/SecuritySettingsController.php'),
            'SystemPreferenceController' => app_path('Http/Controllers/Api/SystemPreferenceController.php'),
            'SlaRuleController' => app_path('Http/Controllers/Api/SlaRuleController.php'),
            'PersonalNoteApiController' => app_path('Http/Controllers/Api/PersonalNoteApiController.php'),
            'EmailFollowUpController' => app_path('Http/Controllers/Api/EmailFollowUpController.php'),
            'VerifierApiController' => app_path('Http/Controllers/Api/VerifierApiController.php'),
            'DspTrackerApiController' => app_path('Http/Controllers/Api/DspTrackerApiController.php'),
        ];

        foreach ($controllers as $name => $path) {
            if (! File::exists($path)) {
                $this->warn("   ⚠ {$name} — file not found");
                continue;
            }

            $content = File::get($path);

            $hasAuditLog = str_contains($content, 'AuditLog') || str_contains($content, 'AuditLogger');
            $hasSystemAudit = str_contains($content, 'SystemAuditLog::record');
            $hasModuleAudit = preg_match('/Audit::create|audit\(\)|->audits\(\)|Audit::log/i', $content);

            $hasUpdate = str_contains($content, 'function update');
            $hasDestroy = str_contains($content, 'function destroy');
            $hasPatch = str_contains($content, 'function patch');
            $hasStore = str_contains($content, 'function store');

            $mutationMethods = array_filter([
                $hasStore ? 'store' : null,
                $hasUpdate ? 'update' : null,
                $hasPatch ? 'patch' : null,
                $hasDestroy ? 'destroy' : null,
            ]);

            $auditCovered = $hasAuditLog || $hasSystemAudit || $hasModuleAudit;

            $status = $auditCovered ? '✅' : '⚠';
            $auditTypes = [];
            if ($hasAuditLog) $auditTypes[] = 'AuditLog/Logger';
            if ($hasSystemAudit) $auditTypes[] = 'SystemAuditLog';
            if ($hasModuleAudit) $auditTypes[] = 'ModuleAudit';

            $this->info("   {$status} {$name}: mutations=[" . implode(',', $mutationMethods) . '] audit=[' . implode(',', $auditTypes ?: ['NONE']) . ']');

            if (! $auditCovered && ! empty($mutationMethods)) {
                $this->report['warnings'][] = "{$name} has mutation methods (" . implode(',', $mutationMethods) . ') but no explicit audit logging (covered by global AuditApiActivity middleware)';
            }
        }

        $this->newLine();
    }

    // ─── AUTO-FIX: Create missing permissions ─────────────────────────────

    private function autoFixPermissions(): void
    {
        $this->info('🔧 AUTO-FIX: Creating missing permissions and assigning to superadmin...');

        $missing = $this->report['permissions']['missing'];
        $created = 0;

        foreach ($missing as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
            $created++;
        }

        if ($created > 0) {
            $this->info("   Created {$created} missing permissions");
        }

        // Assign all permissions to superadmin
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $allPerms = Permission::where('guard_name', 'web')->get();
            $superadmin->syncPermissions($allPerms);
            $this->info('   Synced all permissions to superadmin role');
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('   Cleared permission cache');
        $this->newLine();
    }

    // ─── REPORT GENERATION ────────────────────────────────────────────────

    private function generateReports(): void
    {
        $this->info('📄 Generating compliance reports...');

        $dir = storage_path('app/reports');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');

        // JSON Report
        $jsonPath = "{$dir}/compliance-{$timestamp}.json";
        File::put($jsonPath, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // HTML Report
        $htmlPath = "{$dir}/compliance-{$timestamp}.html";
        File::put($htmlPath, $this->buildHtmlReport());

        $this->info("   📊 JSON: {$jsonPath}");
        $this->info("   📊 HTML: {$htmlPath}");
        $this->newLine();

        // Summary
        $this->info('╔═══════════════════════════════════════════════════╗');
        $this->info('║                  AUDIT SUMMARY                   ║');
        $this->info('╠═══════════════════════════════════════════════════╣');
        $this->info('║ Modules tested:    ' . str_pad(count($this->report['modules_tested']), 28) . '║');
        $this->info('║ API routes:        ' . str_pad($this->report['routes']['total'], 28) . '║');
        $this->info('║ Permissions:       ' . str_pad($this->report['permissions']['total'], 28) . '║');
        $this->info('║ Missing perms:     ' . str_pad(count($this->report['permissions']['missing']), 28) . '║');
        $this->info('║ Audit tables OK:   ' . str_pad(count($this->report['audit_tables']['pass']), 28) . '║');
        $this->info('║ Audit tables FAIL: ' . str_pad(count($this->report['audit_tables']['fail']), 28) . '║');
        $this->info('║ Warnings:          ' . str_pad(count($this->report['warnings']), 28) . '║');
        $this->info('╚═══════════════════════════════════════════════════╝');
    }

    private function buildHtmlReport(): string
    {
        $r = $this->report;
        $modulesHtml = implode('', array_map(fn ($m) => "<li>{$m}</li>", $r['modules_tested']));
        $missingPermsHtml = count($r['permissions']['missing']) > 0
            ? implode('', array_map(fn ($p) => "<li class='text-red-600'>&cross; {$p}</li>", $r['permissions']['missing']))
            : '<li class="text-green-600">&check; All permissions exist</li>';
        $unassignedHtml = count($r['permissions']['unassigned']) > 0
            ? implode('', array_map(fn ($p) => "<li class='text-yellow-600'>&hellip; {$p}</li>", $r['permissions']['unassigned']))
            : '<li class="text-green-600">&check; Superadmin has all</li>';
        $auditPassHtml = implode('', array_map(fn ($t) => "<li class='text-green-600'>&check; {$t}</li>", $r['audit_tables']['pass']));
        $auditFailHtml = implode('', array_map(fn ($t) => "<li class='text-red-600'>&cross; " . $t['table'] . ": " . $t['reason'] . "</li>", $r['audit_tables']['fail']));
        $warningsHtml = count($r['warnings']) > 0
            ? implode('', array_map(fn ($w) => "<li class='text-yellow-600'>&bull; {$w}</li>", $r['warnings']))
            : '<li class="text-green-600">&check; No warnings</li>';

        $dtHtml = '';
        foreach ($r['datatable_features'] as $dt) {
            $sortIcon = $dt['sort'] ? '&check;' : '&cross;';
            $filterIcon = $dt['filter'] ? '&check;' : '&cross;';
            $colIcon = $dt['customize_columns'] ? '&check;' : '&mdash;';
            $editIcon = ($dt['inline_edit'] ?? false) ? '&check;' : '&mdash;';
            $issues = $dt['issues'] ?? '';
            $comp = $dt['component'];
            $dtHtml .= "<tr><td>{$comp}</td><td>{$sortIcon}</td><td>{$filterIcon}</td><td>{$colIcon}</td><td>{$editIcon}</td><td class='text-sm text-gray-500'>{$issues}</td></tr>";
        }

        $generatedAt = $r['generated_at'];
        $routesTotal = $r['routes']['total'];
        $permsTotal = $r['permissions']['total'];
        $missingCount = count($r['permissions']['missing']);
        $warningCount = count($r['warnings']);
        $moduleCount = count($r['modules_tested']);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>CRM Compliance Report</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8 font-sans">
<div class="max-w-5xl mx-auto">
<h1 class="text-3xl font-bold text-gray-900 mb-2">CRM Compliance Audit Report</h1>
<p class="text-sm text-gray-500 mb-8">Generated: {$generatedAt}</p>

<div class="grid grid-cols-4 gap-4 mb-8">
<div class="bg-white rounded-xl shadow p-4 text-center"><p class="text-2xl font-bold text-blue-600">{$routesTotal}</p><p class="text-xs text-gray-500">API Routes</p></div>
<div class="bg-white rounded-xl shadow p-4 text-center"><p class="text-2xl font-bold text-green-600">{$permsTotal}</p><p class="text-xs text-gray-500">Permissions</p></div>
<div class="bg-white rounded-xl shadow p-4 text-center"><p class="text-2xl font-bold text-red-600">{$missingCount}</p><p class="text-xs text-gray-500">Missing Perms</p></div>
<div class="bg-white rounded-xl shadow p-4 text-center"><p class="text-2xl font-bold text-yellow-600">{$warningCount}</p><p class="text-xs text-gray-500">Warnings</p></div>
</div>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">Modules Tested ({$moduleCount})</h2>
<ul class="grid grid-cols-3 gap-1 text-sm">{$modulesHtml}</ul>
</section>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">Missing Permissions</h2>
<ul class="text-sm space-y-1">{$missingPermsHtml}</ul>
</section>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">Superadmin Unassigned Permissions</h2>
<ul class="text-sm space-y-1">{$unassignedHtml}</ul>
</section>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">Audit Tables</h2>
<ul class="text-sm space-y-1">{$auditPassHtml}{$auditFailHtml}</ul>
</section>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">DataTable Features</h2>
<table class="w-full text-sm">
<thead><tr class="border-b"><th class="text-left py-2">Component</th><th>Sort</th><th>Filter</th><th>Col Customize</th><th>Inline Edit</th><th>Issues</th></tr></thead>
<tbody>{$dtHtml}</tbody>
</table>
</section>

<section class="bg-white rounded-xl shadow mb-6 p-6">
<h2 class="text-lg font-semibold mb-3">Warnings</h2>
<ul class="text-sm space-y-1">{$warningsHtml}</ul>
</section>

</div>
</body>
</html>
HTML;
    }

    private function resolveModule(string $uri): string
    {
        $uri = preg_replace('#^api/#', '', $uri);
        $segments = explode('/', $uri);
        return $segments[0] ?? 'unknown';
    }
}
