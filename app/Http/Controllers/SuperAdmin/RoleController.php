<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::orderBy('name')->paginate(30);
        return view('super-admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('super-admin.roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190','unique:roles,name'],
        ]);

        Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        return redirect()->route('super-admin.roles.index')->with('success','Role created');
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        $role->load('permissions');
        return view('super-admin.roles.show', compact('role'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        return view('super-admin.roles.edit', compact('role'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190','unique:roles,name,'.$role->id],
        ]);

        $role->update(['name' => $data['name']]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('super-admin.roles.index')->with('success','Role updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->name === 'superadmin') {
            return back()->with('error','You cannot delete superadmin role');
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('super-admin.roles.index')->with('success','Role deleted');
    }

    public function editPermissions(Role $role)
    {
        $modules = config('permissions.modules');
        $actions = config('permissions.actions');

        // fetch all permission names for lookup
        $allPermissions = Permission::query()->pluck('name')->flip(); // name => index
        $rolePermissions = $role->permissions->pluck('name')->flip();

        return view('super-admin.roles.permissions', compact(
            'role','modules','actions','allPermissions','rolePermissions'
        ));
    }
    
    public function updatePermissions(Request $request, Role $role)
    {
        $selected = $request->input('permissions', []);

        // Prevent accidental removal of superadmin powers (optional)
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Superadmin permissions are handled by Gate::before');
        }

        $role->syncPermissions($selected);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success','Role permissions updated');
        // return redirect()->route('super-admin.roles.permissions.edit', $role)->with('success','Permissions updated');
    }

    public function updatePermissionsModule(Request $request, Role $role, string $module)
    {
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Superadmin should be handled via Gate::before (recommended).');
        }

        $modules = array_keys(config('permissions.modules'));
        if (!in_array($module, $modules, true)) {
            return back()->with('error', 'Invalid module.');
        }

        $selected = $request->input('permissions', []);
        if (!is_array($selected)) $selected = [];

        // Keep only permissions belonging to THIS module
        $selected = array_values(array_filter($selected, fn($p) => str_starts_with($p, "{$module}.")));

        // Keep only permissions that exist in DB (avoid junk)
        $selected = \Spatie\Permission\Models\Permission::query()
            ->whereIn('name', $selected)
            ->pluck('name')
            ->toArray();

        // Current permissions of role
        $current = $role->permissions->pluck('name')->toArray();

        // Remove old permissions for THIS module only
        $currentWithoutModule = array_values(array_filter(
            $current,
            fn($p) => !str_starts_with($p, "{$module}.")
        ));

        // Merge: keep other modules as-is, replace only this module
        $final = array_values(array_unique(array_merge($currentWithoutModule, $selected)));

        $role->syncPermissions($final);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Permissions updated for module: {$module}");
    }
}
