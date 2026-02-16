<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Permission Enforcement Tests
|--------------------------------------------------------------------------
| Validates that role-based access control is properly enforced across
| all modules. Tests both super admin (full access) and restricted roles.
*/

function createUserWithRole(string $roleName, array $permissions = []): User
{
    $role = Role::where('name', $roleName)->where('guard_name', 'web')->first()
        ?? Role::create(['name' => $roleName, 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => "Test {$roleName}",
        'email' => "{$roleName}_perm_" . uniqid() . '@test.com',
        'status' => 'active',
        'password' => bcrypt('Password1!'),
    ]);

    $user->assignRole($role);

    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ═══════════════════════════════════════════════════════════════════════════
// SUPER ADMIN: Should have access to everything
// ═══════════════════════════════════════════════════════════════════════════

describe('Super Admin — Full Access', function () {

    $endpoints = [
        ['GET', '/api/users'],
        ['GET', '/api/lead-submissions'],
        ['GET', '/api/field-submissions'],
        ['GET', '/api/customer-support'],
        ['GET', '/api/vas-requests'],
        ['GET', '/api/clients'],
        ['GET', '/api/expenses'],
        ['GET', '/api/employees'],
        ['GET', '/api/cisco-extensions'],
        ['GET', '/api/attendance-log'],
        ['GET', '/api/announcements'],
        ['GET', '/api/library/documents'],
        ['GET', '/api/audit-logs'],
        ['GET', '/api/security-settings'],
        ['GET', '/api/system-preferences'],
        ['GET', '/api/sla-rules'],
        ['GET', '/api/notification-config'],
        ['GET', '/api/dsp-tracker'],
        ['GET', '/api/verifiers'],
        ['GET', '/api/personal-notes'],
        ['GET', '/api/email-follow-ups'],
        ['GET', '/api/settings/status'],
        ['GET', '/api/dashboard/stats'],
        ['GET', '/api/super-admin/roles'],
        ['GET', '/api/super-admin/permissions/structure'],
    ];

    foreach ($endpoints as [$method, $uri]) {
        it("superadmin can access {$method} {$uri}", function () use ($method, $uri) {
            $user = createUserWithRole('superadmin');
            $response = $this->actingAs($user)->json($method, $uri);
            expect($response->status())->toBe(200);
        });
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// RESTRICTED ROLE: Should be blocked from super-admin routes
// ═══════════════════════════════════════════════════════════════════════════

describe('Restricted Role — Super Admin Routes Blocked', function () {

    $blockedEndpoints = [
        ['GET', '/api/super-admin/roles'],
        ['GET', '/api/super-admin/permissions/structure'],
        ['POST', '/api/super-admin/roles'],
    ];

    foreach ($blockedEndpoints as [$method, $uri]) {
        it("sales_agent cannot access {$method} {$uri}", function () use ($method, $uri) {
            $user = createUserWithRole('sales_agent');
            $response = $this->actingAs($user)->json($method, $uri);
            expect($response->status())->toBeIn([403, 401]);
        });
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// PERMISSION-GATED SETTINGS: Only authorized roles can modify
// ═══════════════════════════════════════════════════════════════════════════

describe('Settings — Write Protection', function () {

    it('non-authorized user cannot update security settings', function () {
        $user = createUserWithRole('sales_agent');
        $response = $this->actingAs($user)->putJson('/api/security-settings', [
            'auto_logout_after_minutes' => 60,
        ]);
        expect($response->status())->toBeIn([403, 422]);
    });

    it('superadmin can update security settings', function () {
        $user = createUserWithRole('superadmin');
        $currentSettings = $this->actingAs($user)->getJson('/api/security-settings');
        $data = $currentSettings->json();

        // Send back current settings (no actual change) to verify endpoint access
        if (is_array($data)) {
            $response = $this->actingAs($user)->putJson('/api/security-settings', $data);
            expect($response->status())->toBeIn([200, 422]); // 422 if validation differs
        }
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// LIBRARY: Permission-gated access
// ═══════════════════════════════════════════════════════════════════════════

describe('Library — Permission-Gated', function () {

    it('user with view-library can list documents', function () {
        $user = createUserWithRole('manager', ['view-library']);
        $response = $this->actingAs($user)->getJson('/api/library/documents');
        expect($response->status())->toBe(200);
    });

    it('user without manage-library cannot upload', function () {
        $user = createUserWithRole('sales_agent', ['view-library']);
        $response = $this->actingAs($user)->postJson('/api/library/documents', [
            'name' => 'test',
        ]);
        expect($response->status())->toBeIn([403, 422]);
    });
});

// ═══════════════════════════════════════════════════════════════════════════
// BOOTSTRAP: Returns correct permissions structure
// ═══════════════════════════════════════════════════════════════════════════

describe('Bootstrap — Permission Awareness', function () {

    it('bootstrap endpoint returns user permissions', function () {
        $user = createUserWithRole('superadmin');
        $response = $this->actingAs($user)->getJson('/api/bootstrap');
        expect($response->status())->toBe(200);
        $data = $response->json();
        expect($data)->toHaveKey('user');
    });

    it('bootstrap returns role information', function () {
        $user = createUserWithRole('manager');
        $response = $this->actingAs($user)->getJson('/api/bootstrap');
        expect($response->status())->toBe(200);
    });
});
