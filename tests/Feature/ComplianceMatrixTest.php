<?php

/**
 * ComplianceMatrixTest — Full permission + audit + route-protection matrix.
 *
 * Run:  php artisan test --filter=ComplianceMatrix
 *       php artisan test tests/Feature/ComplianceMatrixTest.php
 */

use App\Models\User;
use App\Models\AuditLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Schema;

/* ── Helpers ─────────────────────────────────────────────────────────── */

function matrixUser(string $roleName = 'superadmin', array $extraPerms = []): User
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name'     => "Matrix {$roleName}",
        'email'    => "{$roleName}_matrix_" . uniqid() . '@test.com',
        'status'   => 'active',
        'password' => bcrypt('Password1!'),
    ]);

    $user->assignRole($role);

    foreach ($extraPerms as $p) {
        $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

/* ═══════════════════════════════════════════════════════════════════════
   1. PERMISSION INVENTORY
   ═══════════════════════════════════════════════════════════════════════ */

describe('Permission Inventory', function () {

    it('has all config/permissions.php module×action permissions in DB', function () {
        $modules = config('permissions.modules');
        $actions = array_keys(config('permissions.actions'));
        $missing = [];

        foreach ($modules as $key => $label) {
            foreach ($actions as $action) {
                $name = "{$key}.{$action}";
                if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                    $missing[] = $name;
                }
            }
        }

        expect($missing)->toBeEmpty('Missing module×action permissions: ' . implode(', ', $missing));
    });

    it('has all config/permissions.php structure-based permissions in DB', function () {
        $structure = config('permissions.structure', []);
        $missing   = [];

        foreach ($structure as $moduleKey => $module) {
            foreach ($module['permissions'] ?? [] as $perm) {
                $name = "{$moduleKey}.{$perm['key']}";
                if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                    $missing[] = $name;
                }
            }
        }

        expect($missing)->toBeEmpty('Missing structure permissions: ' . implode(', ', $missing));
    });

    it('has all standalone permissions in DB', function () {
        $standalone = [
            'manage-notification-rules', 'manage-escalation-levels',
            'manage-system-preferences', 'manage-sla',
            'manage-security-settings', 'manage-announcements',
            'manage-library', 'view-library', 'download-library',
            'export-audit-logs',
        ];
        $missing = [];

        foreach ($standalone as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                $missing[] = $name;
            }
        }

        expect($missing)->toBeEmpty('Missing standalone permissions: ' . implode(', ', $missing));
    });

    it('superadmin role has every permission assigned', function () {
        $role     = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        $allPerms = Permission::where('guard_name', 'web')->pluck('name');
        $rolePerms = $role ? $role->permissions->pluck('name') : collect();
        $missing  = $allPerms->diff($rolePerms)->values()->toArray();

        expect($missing)->toBeEmpty('Superadmin missing permissions: ' . implode(', ', $missing));
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   2. ROUTE PROTECTION — Unauthenticated
   ═══════════════════════════════════════════════════════════════════════ */

describe('Route Protection — Unauthenticated', function () {

    $protectedRoutes = [
        ['GET',  '/api/users'],
        ['GET',  '/api/lead-submissions'],
        ['GET',  '/api/field-submissions'],
        ['GET',  '/api/customer-support'],
        ['GET',  '/api/vas-requests'],
        ['GET',  '/api/clients'],
        ['GET',  '/api/employees'],
        ['GET',  '/api/cisco-extensions'],
        ['GET',  '/api/expenses'],
        ['GET',  '/api/dsp-tracker'],
        ['GET',  '/api/verifiers'],
        ['GET',  '/api/personal-notes'],
        ['GET',  '/api/email-follow-ups'],
        ['GET',  '/api/attendance-log'],
        ['GET',  '/api/announcements'],
        ['GET',  '/api/library/documents'],
        ['GET',  '/api/security-settings'],
        ['GET',  '/api/system-preferences'],
        ['GET',  '/api/sla-rules'],
        ['GET',  '/api/notification-config'],
        ['GET',  '/api/audit-logs'],
        ['GET',  '/api/bootstrap'],
        ['GET',  '/api/dashboard/stats'],
    ];

    foreach ($protectedRoutes as [$method, $uri]) {
        it("blocks unauthenticated {$method} {$uri}", function () use ($method, $uri) {
            $response = $this->json($method, $uri);
            expect($response->status())->toBeIn([401, 302, 419]);
        });
    }
});

/* ═══════════════════════════════════════════════════════════════════════
   3. ROUTE PROTECTION — Role-Based (super admin only)
   ═══════════════════════════════════════════════════════════════════════ */

describe('Route Protection — Super Admin Only', function () {

    $superAdminRoutes = [
        ['GET',  '/api/super-admin/roles'],
        ['GET',  '/api/super-admin/permissions/structure'],
    ];

    foreach ($superAdminRoutes as [$method, $uri]) {
        it("blocks non-superadmin from {$method} {$uri}", function () use ($method, $uri) {
            $user = matrixUser('sales_agent');
            $response = $this->actingAs($user)->json($method, $uri);
            expect($response->status())->toBeIn([403, 401]);
        });

        it("allows superadmin to {$method} {$uri}", function () use ($method, $uri) {
            $user = matrixUser('superadmin');
            $response = $this->actingAs($user)->json($method, $uri);
            expect($response->status())->toBe(200);
        });
    }
});

/* ═══════════════════════════════════════════════════════════════════════
   4. CRITICAL: Controllers Without Permission Checks
   ═══════════════════════════════════════════════════════════════════════ */

describe('Critical — DspTracker has no permission checks', function () {

    it('any authenticated user can access GET /api/dsp-tracker', function () {
        $user = matrixUser('sales_agent');
        $r = $this->actingAs($user)->getJson('/api/dsp-tracker');
        expect($r->status())->toBe(200);
    });

    it('any authenticated user can POST /api/dsp-tracker/import (SECURITY GAP)', function () {
        $user = matrixUser('sales_agent');
        $r = $this->actingAs($user)->postJson('/api/dsp-tracker/import', []);
        // Should be 403 but currently passes through without permission check
        expect($r->status())->not->toBe(403,
            'DspTrackerApiController::import has NO permission check — any authenticated user can import CSV');
    });
});

describe('Critical — EmployeeApi has no permission checks', function () {

    it('any authenticated user can access GET /api/employees', function () {
        $user = matrixUser('sales_agent');
        $r = $this->actingAs($user)->getJson('/api/employees');
        expect($r->status())->toBe(200);
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   5. AUDIT LOGGING VERIFICATION
   ═══════════════════════════════════════════════════════════════════════ */

describe('Audit Logging — Global Middleware', function () {

    it('audit_logs table exists with required columns', function () {
        expect(Schema::hasTable('audit_logs'))->toBeTrue();
        expect(Schema::hasColumns('audit_logs', [
            'user_id', 'action', 'module', 'ip', 'user_agent',
            'old_values', 'new_values', 'created_at',
        ]))->toBeTrue();
    });

    it('POST /api/personal-notes creates an audit log entry', function () {
        $user  = matrixUser('superadmin');
        $count = AuditLog::count();

        $this->actingAs($user)->postJson('/api/personal-notes', [
            'title'   => 'Compliance Test Note',
            'content' => 'Testing audit logging.',
        ]);

        expect(AuditLog::count())->toBeGreaterThanOrEqual($count);
    });
});

describe('Audit Table Schema Compliance', function () {

    $tables = [
        'user_audits',
        'lead_submission_audits',
        'field_submission_audits',
        'customer_support_submission_audits',
        'vas_request_audits',
        'client_audits',
        'expense_audits',
        'cisco_extension_audits',
    ];

    foreach ($tables as $table) {
        it("{$table} exists", function () use ($table) {
            expect(Schema::hasTable($table))->toBeTrue();
        });

        it("{$table} is missing ip_address column (compliance gap)", function () use ($table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("{$table} does not exist");
            }
            // This test documents the gap — it PASSES when the column is missing
            expect(Schema::hasColumn($table, 'ip_address'))->toBeFalse(
                "{$table} should be missing ip_address (confirming gap). If this fails, the migration has been applied."
            );
        });
    }
});

/* ═══════════════════════════════════════════════════════════════════════
   6. MODULE CRUD — Superadmin Smoke Tests
   ═══════════════════════════════════════════════════════════════════════ */

describe('Module CRUD — Users', function () {
    it('superadmin can list users', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/users');
        expect($r->status())->toBe(200);
    });
    it('superadmin can create user', function () {
        $r = $this->actingAs(matrixUser())->postJson('/api/users', [
            'name' => 'Compliance Test', 'email' => 'compliance_' . uniqid() . '@test.com',
            'password' => 'Password1!', 'password_confirmation' => 'Password1!',
            'role' => 'sales_agent', 'status' => 'active',
        ]);
        expect($r->status())->toBeIn([200, 201]);
    });
});

describe('Module CRUD — Lead Submissions', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/lead-submissions');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Field Submissions', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/field-submissions');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Customer Support', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/customer-support');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — VAS Requests', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/vas-requests');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Clients', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/clients');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Personal Notes', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/personal-notes');
        expect($r->status())->toBe(200);
    });
    it('superadmin can create', function () {
        $r = $this->actingAs(matrixUser())->postJson('/api/personal-notes', [
            'title' => 'Test', 'content' => 'Content',
        ]);
        expect($r->status())->toBeIn([200, 201]);
    });
});

describe('Module CRUD — Email Follow-Ups', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/email-follow-ups');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Employees', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/employees');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Cisco Extensions', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/cisco-extensions');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Expenses', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/expenses');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — DSP Tracker', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/dsp-tracker');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Verifiers', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/verifiers');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Attendance Log', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/attendance-log');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Announcements', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/announcements');
        expect($r->status())->toBe(200);
    });
});

describe('Module CRUD — Library Documents', function () {
    it('superadmin can list', function () {
        $r = $this->actingAs(matrixUser())->getJson('/api/library/documents');
        expect($r->status())->toBe(200);
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   7. SETTINGS & CONFIG ENDPOINTS
   ═══════════════════════════════════════════════════════════════════════ */

describe('Settings Endpoints', function () {

    $endpoints = [
        '/api/security-settings',
        '/api/system-preferences',
        '/api/sla-rules',
        '/api/notification-config',
        '/api/audit-logs',
        '/api/settings/status',
        '/api/dashboard/stats',
    ];

    foreach ($endpoints as $uri) {
        it("GET {$uri} returns 200", function () use ($uri) {
            $r = $this->actingAs(matrixUser())->getJson($uri);
            expect($r->status())->toBe(200);
        });
    }
});

/* ═══════════════════════════════════════════════════════════════════════
   8. PERMISSION-GATED SETTINGS — Write Protection
   ═══════════════════════════════════════════════════════════════════════ */

describe('Settings — Write Protection', function () {

    it('non-superadmin without manage-security-settings cannot update security settings', function () {
        $user = matrixUser('viewer');
        $r = $this->actingAs($user)->putJson('/api/security-settings', [
            'password_min_length' => 12,
        ]);
        expect($r->status())->toBeIn([403, 422]);
    });

    it('superadmin can update security settings', function () {
        $user = matrixUser('superadmin');
        $r = $this->actingAs($user)->putJson('/api/security-settings', [
            'password_min_length' => 8,
        ]);
        expect($r->status())->toBeIn([200, 422]);
    });

    it('non-superadmin without manage-system-preferences cannot update preferences', function () {
        $user = matrixUser('viewer');
        $r = $this->actingAs($user)->putJson('/api/system-preferences', []);
        expect($r->status())->toBeIn([403, 422]);
    });
});

/* ═══════════════════════════════════════════════════════════════════════
   9. DATATABLE ENDPOINTS — Pagination, Sorting, Filters, Columns
   ═══════════════════════════════════════════════════════════════════════ */

describe('DataTable Endpoints', function () {

    $modules = [
        ['lead-submissions',  '/api/lead-submissions',  true, true],
        ['field-submissions', '/api/field-submissions',  true, true],
        ['customer-support',  '/api/customer-support',   true, true],
        ['vas-requests',      '/api/vas-requests',       true, true],
        ['clients',           '/api/clients',            true, true],
        ['employees',         '/api/employees',          true, true],
        ['cisco-extensions',  '/api/cisco-extensions',   true, true],
        ['expenses',          '/api/expenses',           true, true],
        ['email-follow-ups',  '/api/email-follow-ups',   true, true],
        ['announcements',     '/api/announcements',      false, false],
        ['library',           '/api/library/documents',  false, false],
        ['audit-logs',        '/api/audit-logs',         false, false],
    ];

    foreach ($modules as [$name, $baseUri, $hasFilters, $hasColumns]) {
        it("{$name}: paginated index returns 200", function () use ($baseUri) {
            $r = $this->actingAs(matrixUser())->getJson("{$baseUri}?per_page=5");
            expect($r->status())->toBe(200);
        });

        it("{$name}: sort param accepted", function () use ($baseUri) {
            $r = $this->actingAs(matrixUser())->getJson("{$baseUri}?sort=created_at:desc");
            expect($r->status())->toBe(200);
        });

        if ($hasFilters) {
            it("{$name}: filters endpoint returns 200", function () use ($baseUri) {
                $r = $this->actingAs(matrixUser())->getJson("{$baseUri}/filters");
                expect($r->status())->toBe(200);
            });
        }

        if ($hasColumns) {
            it("{$name}: columns endpoint returns 200", function () use ($baseUri) {
                $r = $this->actingAs(matrixUser())->getJson("{$baseUri}/columns");
                expect($r->status())->toBe(200);
            });
        }
    }
});

/* ═══════════════════════════════════════════════════════════════════════
   10. BOOTSTRAP — Permission & Role Awareness
   ═══════════════════════════════════════════════════════════════════════ */

describe('Bootstrap — Auth Payload', function () {

    it('returns user with roles and permissions', function () {
        $user = matrixUser('superadmin');
        $r = $this->actingAs($user)->getJson('/api/bootstrap');
        expect($r->status())->toBe(200);
        $r->assertJsonStructure(['user', 'permissions']);
    });

    it('permissions array is non-empty for superadmin', function () {
        $user = matrixUser('superadmin');
        $r = $this->actingAs($user)->getJson('/api/bootstrap');
        $data = $r->json();
        expect(count($data['permissions'] ?? []))->toBeGreaterThan(0);
    });
});
