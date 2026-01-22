<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::query()->orderBy('name')->paginate(50);
        return view('super-admin.permissions.index', compact('permissions'));
    }

    public function create()
    {
        $modules = config('permissions.modules');
        $actions = config('permissions.actions');

        return view('super-admin.permissions.create', compact('modules','actions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required','in:custom,module_actions'],

            'custom_name' => ['nullable','string','max:190'],

            'module' => ['nullable','string','max:100'],
            'actions' => ['nullable','array'],
            'actions.*' => ['string'],
        ]);

        $created = [];

        if ($data['mode'] === 'custom') {
            $name = trim((string)($data['custom_name'] ?? ''));

            if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)$/i', $name)) {
                return back()
                    ->withInput()
                    ->withErrors(['custom_name' => 'Permission must be like module.action (example: accounts.edit)']);
            }

            $perm = Permission::firstOrCreate([
                'name' => strtolower($name),
                'guard_name' => 'web',
            ]);

            $created[] = $perm->name;
        }

        if ($data['mode'] === 'module_actions') {
            $modules = array_keys(config('permissions.modules'));
            $validActions = array_keys(config('permissions.actions'));

            $module = (string)($data['module'] ?? '');
            $actions = $data['actions'] ?? [];

            if (!in_array($module, $modules, true)) {
                return back()->withInput()->withErrors(['module' => 'Invalid module selected']);
            }

            // keep only valid actions
            $actions = array_values(array_intersect($actions, $validActions));

            if (count($actions) === 0) {
                return back()->withInput()->withErrors(['actions' => 'Select at least one action']);
            }

            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $perm = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $created[] = $perm->name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('super-admin.permissions.index')
            ->with('success', 'Created: '.implode(', ', $created));
    }

    public function show(Permission $permission)
    {
        return view('super-admin.permissions.show', compact('permission'));
    }

    public function edit(Permission $permission)
    {
        return view('super-admin.permissions.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190','unique:permissions,name,'.$permission->id],
        ]);

        $name = strtolower(trim($data['name']));
        if (!preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)$/i', $name)) {
            return back()->withInput()->withErrors(['name' => 'Permission must be like module.action (example: accounts.edit)']);
        }

        $permission->update(['name' => $name]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('super-admin.permissions.index')->with('success', 'Permission updated');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('super-admin.permissions.index')->with('success', 'Permission deleted');
    }
}
