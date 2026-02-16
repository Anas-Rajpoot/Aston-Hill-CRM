<?php

/**
 * Permission Seeder Compliance Patch
 *
 * This file can be run standalone or its contents merged into PermissionSeeder.php.
 *
 * It ensures all permissions referenced by controllers but potentially missing from
 * the seeder are created, and syncs the superadmin role with all permissions.
 *
 * Run standalone:  php patches/permission-seeder-compliance.php
 * Or merge into:   database/seeders/PermissionSeeder.php
 */

// When running standalone, bootstrap Laravel
if (! function_exists('app')) {
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "=== Permission Seeder Compliance Patch ===\n\n";

/*
|--------------------------------------------------------------------------
| 1. Ensure all controller-referenced permissions exist
|--------------------------------------------------------------------------
| Some controllers reference permissions that may not be in config/permissions.php
| or may have been created at runtime. This ensures they exist in DB.
*/

$controllerPermissions = [
    // DspTrackerApiController (CRITICAL: currently has NO permission checks)
    'dsp_tracker.upload_csv',
    'dsp_tracker.delete_existing_csv',
    'dsp_tracker.list',
    'dsp_tracker.search_dsp_status',
    'dsp_tracker.export_dsp_data',

    // EmployeeApiController (CRITICAL: currently has NO permission checks)
    'users.list',
    'users.create',
    'users.edit',
    'users.delete',
    'users.bulk_upload_employees',
    'users.assign_extensions',

    // AttendanceLogApiController
    'view_attendance_logs',
    'force_logout',
    'export_attendance_data',

    // ExpenseApiController
    'expense_tracker.list',
    'expense_tracker.view',
    'expense_tracker.create',
    'expense_tracker.edit',
    'expense_tracker.update',
    'expense_tracker.delete',
    'expense_tracker.export_expenses',
    'expense_tracker.export',

    // VerifierApiController
    'verifiers.add',
    'verifiers.create',
    'verifiers.delete',
    'gsm_verifiers.list',
    'gsm_verifiers.add_verifier',
    'gsm_verifiers.edit',
    'gsm_verifiers.delete',

    // Standalone settings permissions
    'manage-notification-rules',
    'manage-escalation-levels',
    'manage-system-preferences',
    'manage-sla',
    'manage-security-settings',
    'manage-announcements',
    'manage-library',
    'view-library',
    'download-library',
    'export-audit-logs',

    // Granular notification permissions
    'notification_rules.edit_settings',
    'notification_rules.manage_channels',
    'notification_rules.manage_triggers',
    'notification_rules.manage_escalations',
    'notification_rules.manage_templates',
    'notification_rules.view_logs',
    'notification_rules.send_test',
    'notification_rules.delete',
    'notification_rules.delete_logs',
];

$created = 0;
$existed = 0;

foreach ($controllerPermissions as $name) {
    $perm = Permission::firstOrCreate([
        'name'       => $name,
        'guard_name' => 'web',
    ]);

    if ($perm->wasRecentlyCreated) {
        echo "  [CREATED] {$name}\n";
        $created++;
    } else {
        $existed++;
    }
}

echo "\n  Total: {$created} created, {$existed} already existed.\n";

/*
|--------------------------------------------------------------------------
| 2. Sync superadmin with ALL permissions
|--------------------------------------------------------------------------
*/

echo "\n--- Syncing superadmin role ---\n";

$superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
$allPerms   = Permission::where('guard_name', 'web')->pluck('name')->toArray();
$superadmin->syncPermissions($allPerms);

echo "  Superadmin now has " . count($allPerms) . " permissions.\n";

/*
|--------------------------------------------------------------------------
| 3. Clear permission cache
|--------------------------------------------------------------------------
*/

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "\n=== Done. Permission cache cleared. ===\n";
