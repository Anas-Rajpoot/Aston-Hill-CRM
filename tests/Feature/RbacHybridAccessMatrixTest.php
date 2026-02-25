<?php

use App\Models\Role;
use App\Models\User;
use App\Support\RbacPermission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function hybridUser(string $roleName, array $permissionNames = []): User
{
    $role = Role::query()->firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create([
        'email' => "{$roleName}_" . uniqid() . '@test.local',
        'status' => 'active',
    ]);
    $user->assignRole($role);

    if (! empty($permissionNames)) {
        $ids = [];
        foreach ($permissionNames as $name) {
            $perm = Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
            $ids[] = (int) $perm->id;
        }

        DB::table('role_has_permissions')->where('role_id', (int) $role->id)->delete();
        foreach (array_unique($ids) as $permissionId) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $permissionId,
                'role_id' => (int) $role->id,
            ]);
        }
    }

    return $user->fresh();
}

it('supports legacy alias checks through canonical read action', function () {
    $user = hybridUser('alias_reader', ['teams.list']);

    expect(RbacPermission::can($user, 'teams', 'read', ['teams.list']))->toBeTrue();
});

it('resolves inherited permissions through role inheritance graph', function () {
    $parent = Role::query()->firstOrCreate(['name' => 'parent_role', 'guard_name' => 'web']);
    $child = Role::query()->firstOrCreate(['name' => 'child_role', 'guard_name' => 'web']);

    $perm = Permission::query()->firstOrCreate(['name' => 'dsp_tracker.read', 'guard_name' => 'web']);
    DB::table('role_has_permissions')->updateOrInsert([
        'permission_id' => (int) $perm->id,
        'role_id' => (int) $parent->id,
    ], []);

    DB::table('role_inheritance')->updateOrInsert([
        'parent_role_id' => (int) $parent->id,
        'child_role_id' => (int) $child->id,
    ], [
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($child);

    expect(RbacPermission::can($user->fresh(), 'dsp_tracker', 'read', ['dsp_tracker.list']))->toBeTrue();
});

it('blocks dsp tracker list without read permission', function () {
    $user = hybridUser('restricted_dsp_user');

    $response = $this->actingAs($user)->getJson('/api/dsp-tracker');
    expect($response->status())->toBe(403);
});

it('blocks verifier create without create permission', function () {
    $user = hybridUser('restricted_verifier_user');

    $response = $this->actingAs($user)->postJson('/api/verifiers', [
        'verifier_name' => 'Verifier Test',
        'verifier_number' => '971501234567',
    ]);
    expect($response->status())->toBe(403);
});

it('protects superadmin role from deletion', function () {
    $user = hybridUser('superadmin', ['roles.assign_permissions', 'roles.delete']);

    $superadminRole = Role::query()->firstOrCreate([
        'name' => 'superadmin',
        'guard_name' => 'web',
    ]);

    $response = $this->actingAs($user)->deleteJson('/api/super-admin/roles/' . $superadminRole->id);
    expect($response->status())->toBe(422);
});

