<?php

use App\Models\User;
use App\Models\AuditLog;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| CRM Compliance Test Suite
|--------------------------------------------------------------------------
| Validates:
| 1. Permission matrix: every config/permissions.php permission exists in DB
| 2. Permission assignment: superadmin has ALL permissions
| 3. Route protection: unauthenticated → 401, unauthorized → 403
| 4. Audit logging: all mutations log to audit_logs table
| 5. Module CRUD: create, read, update, delete for key modules
*/

// ─── Helper: create authenticated user with role ──────────────────────────
function loginAs(string $roleName = 'superadmin'): User
{
    $role = Role::where('name', $roleName)->where('guard_name', 'web')->first()
        ?? Role::create(['name' => $roleName, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name'     => "Test {$roleName}",
        'email'    => "{$roleName}_test_" . uniqid() . '@test.com',
        'status'   => 'active',
        'password' => bcrypt('Password1!'),
    ]);

    $user->assignRole($role);

    return $user;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1: PERMISSION INVENTORY VALIDATION
// ═══════════════════════════════════════════════════════════════════════════

describe('Permission Inventory', function () {

    it('has all config/permissions.php module×action permissions in DB', function () {
        $modules = config('permissions.modules');
        $actions = array_keys(config('permissions.actions'));
        $missing = [];

        foreach ($modules as $moduleKey => $label) {
            foreach ($actions as $action) {
                $permName = "{$moduleKey}.{$action}";
                if (! Permission::where('name', $permName)->where('guard_name', 'web')->exists()) {
                    $missing[] = $permName;
                }
            }
        }

        expect($missing)->toBeEmpty(
            'Missing permissions in DB: ' . implode(', ', $missing)
        );
    });

    it('has all structure-based granular permissions in DB', function () {
        $structure = config('permissions.structure', []);
        $missing = [];

        foreach ($structure as $moduleKey => $module) {
            foreach ($module['permissions'] ?? [] as $perm) {
                $permName = "{$moduleKey}.{$perm['key']}";
                if (! Permission::where('name', $permName)->where('guard_name', 'web')->exists()) {
                    $missing[] = $permName;
                }
            }
        }

        expect($missing)->toBeEmpty(
            'Missing structure permissions in DB: ' . implode(', ', $missing)
        );
    });

    it('has all standalone permissions in DB', function () {
        $standalone = [
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
        ];
        $missing = [];

        foreach ($standalone as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                $missing[] = $perm;
            }
        }

        expect($missing)->toBeEmpty(
            'Missing standalone permissions: ' . implode(', ', $missing)
        );
    });

    it('superadmin role has all permissions assigned', function () {
        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();

        expect($superadmin)->not->toBeNull('superadmin role must exist');

        $allPerms = Permission::where('guard_name', 'web')->pluck('name');
        $rolePerms = $superadmin->permissions->pluck('name');

        $missing = $allPerms->diff($rolePerms);

        expect($missing)->toBeEmpty(
            'Superadmin missing permissions: ' . $missing->implode(', ')
        );
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2: ROUTE PROTECTION (Authentication & Authorization)
// ═══════════════════════════════════════════════════════════════════════════

describe('Route Protection — Unauthenticated Access', function () {

    $protectedRoutes = [
        ['GET',    '/api/users'],
        ['GET',    '/api/lead-submissions'],
        ['GET',    '/api/field-submissions'],
        ['GET',    '/api/customer-support'],
        ['GET',    '/api/vas-requests'],
        ['GET',    '/api/clients'],
        ['GET',    '/api/expenses'],
        ['GET',    '/api/employees'],
        ['GET',    '/api/cisco-extensions'],
        ['GET',    '/api/attendance-log'],
        ['GET',    '/api/dsp-tracker'],
        ['GET',    '/api/verifiers'],
        ['GET',    '/api/personal-notes'],
        ['GET',    '/api/email-follow-ups'],
        ['GET',    '/api/announcements'],
        ['GET',    '/api/library/documents'],
        ['GET',    '/api/audit-logs'],
        ['GET',    '/api/security-settings'],
        ['GET',    '/api/system-preferences'],
        ['GET',    '/api/sla-rules'],
        ['GET',    '/api/notification-config'],
        ['GET',    '/api/dashboard/stats'],
        ['GET',    '/api/settings/status'],
        ['POST',   '/api/users'],
        ['POST',   '/api/expenses'],
        ['POST',   '/api/announcements'],
        ['DELETE',  '/api/users/1'],
    ];

    foreach ($protectedRoutes as [$method, $uri]) {
        it("blocks unauthenticated {$method} {$uri}", function () use ($method, $uri) {
            $response = $this->json($method, $uri);
            expect($response->status())->toBeIn([401, 302, 419]);
        });
    }
});

describe('Route Protection — Super Admin Only Routes', function () {

    it('blocks non-superadmin from super-admin/roles', function () {
        $user = loginAs('manager');

        $response = $this->actingAs($user)
            ->getJson('/api/super-admin/roles');

        expect($response->status())->toBeIn([403, 401]);
    });

    it('blocks non-superadmin from permissions structure', function () {
        $user = loginAs('manager');

        $response = $this->actingAs($user)
            ->getJson('/api/super-admin/permissions/structure');

        expect($response->status())->toBeIn([403, 401]);
    });

    it('allows superadmin to access roles', function () {
        $user = loginAs('superadmin');

        $response = $this->actingAs($user)
            ->getJson('/api/super-admin/roles');

        expect($response->status())->toBe(200);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3: AUDIT LOGGING VALIDATION
// ═══════════════════════════════════════════════════════════════════════════

describe('Audit Logging', function () {

    it('logs POST requests to audit_logs table', function () {
        $user = loginAs('superadmin');
        $countBefore = AuditLog::count();

        $this->actingAs($user)
            ->postJson('/api/personal-notes', [
                'title'   => 'Test Audit Note',
                'content' => 'This is a test note for audit logging',
            ]);

        expect(AuditLog::count())->toBeGreaterThan($countBefore);

        $log = AuditLog::latest('id')->first();
        expect($log->action)->toBe('created');
        expect($log->module)->toBe('Personal Notes');
        expect($log->user_id)->toBe($user->id);
        expect($log->user_name)->toBe($user->name);
        expect($log->ip)->not->toBeNull();
        expect($log->user_agent)->not->toBeNull();
    });

    it('logs PUT/PATCH requests with new_values', function () {
        $user = loginAs('superadmin');

        // Create a note first
        $response = $this->actingAs($user)
            ->postJson('/api/personal-notes', [
                'title'   => 'Note to Update',
                'content' => 'Original content',
            ]);
        $noteId = $response->json('data.id') ?? $response->json('id');

        if ($noteId) {
            $countBefore = AuditLog::count();

            $this->actingAs($user)
                ->putJson("/api/personal-notes/{$noteId}", [
                    'title'   => 'Updated Title',
                    'content' => 'Updated content',
                ]);

            expect(AuditLog::count())->toBeGreaterThan($countBefore);

            $log = AuditLog::latest('id')->first();
            expect($log->action)->toBe('updated');
            expect($log->new_values)->not->toBeNull();
        }
    });

    it('logs DELETE requests', function () {
        $user = loginAs('superadmin');

        $response = $this->actingAs($user)
            ->postJson('/api/personal-notes', [
                'title'   => 'Note to Delete',
                'content' => 'Will be deleted',
            ]);
        $noteId = $response->json('data.id') ?? $response->json('id');

        if ($noteId) {
            $countBefore = AuditLog::count();

            $this->actingAs($user)
                ->deleteJson("/api/personal-notes/{$noteId}");

            expect(AuditLog::count())->toBeGreaterThan($countBefore);

            $log = AuditLog::latest('id')->first();
            expect($log->action)->toBe('deleted');
        }
    });

    it('audit log has all required columns', function () {
        $columns = \Schema::getColumnListing('audit_logs');

        $required = [
            'id', 'user_id', 'user_name', 'user_role', 'action', 'module',
            'result', 'ip', 'user_agent', 'session_id', 'route', 'method',
            'old_values', 'new_values', 'occurred_at',
        ];

        foreach ($required as $col) {
            expect($columns)->toContain($col, "audit_logs table missing column: {$col}");
        }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4: MODULE-SPECIFIC AUDIT TABLES VALIDATION
// ═══════════════════════════════════════════════════════════════════════════

describe('Module Audit Tables', function () {

    $auditTables = [
        'lead_submission_audits' => ['lead_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'field_submission_audits' => ['field_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'vas_request_audits' => ['vas_request_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'customer_support_submission_audits' => ['customer_support_submission_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'client_audits' => ['client_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'expense_audits' => ['expense_id', 'user_id', 'column_name', 'old_value', 'new_value'],
        'user_audits' => ['user_id'],
        'cisco_extension_audits' => ['cisco_extension_id', 'user_id', 'column_name', 'old_value', 'new_value'],
    ];

    foreach ($auditTables as $table => $requiredColumns) {
        it("has {$table} table with required columns", function () use ($table, $requiredColumns) {
            expect(\Schema::hasTable($table))->toBeTrue("{$table} table does not exist");

            $columns = \Schema::getColumnListing($table);
            foreach ($requiredColumns as $col) {
                expect($columns)->toContain($col, "{$table} missing column: {$col}");
            }
        });
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 5: MODULE CRUD TESTS
// ═══════════════════════════════════════════════════════════════════════════

describe('Module CRUD — Personal Notes', function () {

    it('can list personal notes', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/personal-notes');
        expect($response->status())->toBe(200);
    });

    it('can create a personal note', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->postJson('/api/personal-notes', [
            'title'   => 'Test Note',
            'content' => 'Test content here',
        ]);
        expect($response->status())->toBeIn([200, 201]);
    });

    it('can show a personal note', function () {
        $user = loginAs('superadmin');
        $create = $this->actingAs($user)->postJson('/api/personal-notes', [
            'title' => 'Show Test', 'content' => 'Content',
        ]);
        $id = $create->json('data.id') ?? $create->json('id');
        if ($id) {
            $response = $this->actingAs($user)->getJson("/api/personal-notes/{$id}");
            expect($response->status())->toBe(200);
        }
    });

    it('can delete a personal note', function () {
        $user = loginAs('superadmin');
        $create = $this->actingAs($user)->postJson('/api/personal-notes', [
            'title' => 'Delete Test', 'content' => 'Content',
        ]);
        $id = $create->json('data.id') ?? $create->json('id');
        if ($id) {
            $response = $this->actingAs($user)->deleteJson("/api/personal-notes/{$id}");
            expect($response->status())->toBeIn([200, 204]);
        }
    });
});

describe('Module CRUD — Announcements', function () {

    it('can list announcements', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/announcements');
        expect($response->status())->toBe(200);
    });

    it('can create an announcement', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->postJson('/api/announcements', [
            'title'        => 'Test Announcement',
            'body'         => 'Announcement body text',
            'type'         => 'text',
            'visibility'   => 'all',
            'priority'     => 'medium',
            'published_at' => now()->toIso8601String(),
        ]);
        expect($response->status())->toBeIn([200, 201]);
    });
});

describe('Module CRUD — Expenses', function () {

    it('can list expenses', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/expenses');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Users', function () {

    it('can list users', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/users');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Clients', function () {

    it('can list clients', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/clients');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Lead Submissions', function () {

    it('can list lead submissions', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/lead-submissions');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Field Submissions', function () {

    it('can list field submissions', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/field-submissions');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — VAS Requests', function () {

    it('can list VAS requests', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/vas-requests');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Customer Support', function () {

    it('can list customer support tickets', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/customer-support');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Cisco Extensions', function () {

    it('can list cisco extensions', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/cisco-extensions');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Library Documents', function () {

    it('can list library documents', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/library/documents');
        expect($response->status())->toBe(200);
    });
});

describe('Module CRUD — Employees', function () {

    it('can list employees', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/employees');
        expect($response->status())->toBe(200);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 6: SETTINGS & CONFIG ENDPOINTS
// ═══════════════════════════════════════════════════════════════════════════

describe('Settings Endpoints', function () {

    it('can read security settings', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/security-settings');
        expect($response->status())->toBe(200);
    });

    it('can read system preferences', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/system-preferences');
        expect($response->status())->toBe(200);
    });

    it('can read SLA rules', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/sla-rules');
        expect($response->status())->toBe(200);
    });

    it('can read notification config', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/notification-config');
        expect($response->status())->toBe(200);
    });

    it('can read audit logs', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/audit-logs');
        expect($response->status())->toBe(200);
    });

    it('can read settings status', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/settings/status');
        expect($response->status())->toBe(200);
    });

    it('can read dashboard stats', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');
        expect($response->status())->toBe(200);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 7: DATATABLE ENDPOINTS (filters, columns, sort)
// ═══════════════════════════════════════════════════════════════════════════

describe('DataTable Endpoints', function () {

    $datatableModules = [
        'lead-submissions' => ['/api/lead-submissions', '/api/lead-submissions/filters', '/api/lead-submissions/columns'],
        'field-submissions' => ['/api/field-submissions', '/api/field-submissions/filters', '/api/field-submissions/columns'],
        'customer-support' => ['/api/customer-support', '/api/customer-support/filters', '/api/customer-support/columns'],
        'vas-requests' => ['/api/vas-requests', '/api/vas-requests/filters', '/api/vas-requests/columns'],
        'clients' => ['/api/clients', '/api/clients/filters', '/api/clients/columns'],
        'employees' => ['/api/employees', '/api/employees/filters', '/api/employees/columns'],
        'cisco-extensions' => ['/api/cisco-extensions', '/api/cisco-extensions/filters', '/api/cisco-extensions/columns'],
        'expenses' => ['/api/expenses', '/api/expenses/filters', '/api/expenses/columns'],
        'email-follow-ups' => ['/api/email-follow-ups', '/api/email-follow-ups/filters', '/api/email-follow-ups/columns'],
    ];

    foreach ($datatableModules as $module => [$index, $filters, $columns]) {
        it("{$module}: index endpoint returns 200", function () use ($index) {
            $user = loginAs('superadmin');
            $response = $this->actingAs($user)->getJson($index);
            expect($response->status())->toBe(200);
        });

        it("{$module}: filters endpoint returns 200", function () use ($filters) {
            $user = loginAs('superadmin');
            $response = $this->actingAs($user)->getJson($filters);
            expect($response->status())->toBe(200);
        });

        it("{$module}: columns endpoint returns 200", function () use ($columns) {
            $user = loginAs('superadmin');
            $response = $this->actingAs($user)->getJson($columns);
            expect($response->status())->toBe(200);
        });
    }

    it('lead-submissions: sort param works', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/lead-submissions?sort=submitted_at&order=desc');
        expect($response->status())->toBe(200);
    });

    it('expenses: sort param works', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/expenses?sort=expense_date&order=desc');
        expect($response->status())->toBe(200);
    });

    it('clients: search param works', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/clients?company_name=test');
        expect($response->status())->toBe(200);
    });

    it('announcements: sort param works', function () {
        $user = loginAs('superadmin');
        $response = $this->actingAs($user)->getJson('/api/announcements?sort=title:asc');
        expect($response->status())->toBe(200);
    });
});
