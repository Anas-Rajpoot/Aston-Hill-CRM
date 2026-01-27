<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Notifications\UserApprovalStatusNotification;
use Spatie\Permission\PermissionRegistrar;


class UserController extends Controller
{
    public function index()
    {
        $q = request('q');
    $role = request('role');
    $status = request('status');

    $users = User::query()
        ->when($q, fn($qq) => $qq->where(function($w) use ($q){
            $w->where('name','like',"%$q%")->orWhere('email','like',"%$q%");
        }))
        ->when($status, fn($qq) => $qq->where('status', $status))
        ->when($role, function($qq) use ($role){
            $qq->whereHas('roles', fn($r)=>$r->where('name', $role));
        })
        ->latest()
        ->paginate(10);

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));

        // $users = User::latest()->paginate(10);
        // return view('users.index', compact('users'));
    }

    public function show(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('users.show', compact('user', 'roles'));
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('users.edit', compact('user', 'roles'));
    }

    public function review(User $user)
    {
        $roles = Role::whereNotIn('name', ['superadmin'])
                    ->orderBy('name')
                    ->get();
                    
        $userRoleIds = $user->roles()->pluck('id')->toArray();

        return view('users.review', compact('user', 'roles', 'userRoleIds'));
    }
    
    public function approve(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,pending,rejected',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',

            'rejection_reason' => 'nullable|string|max:255',
        ]);
        
        if ($validated['status'] === 'approved') {
            $request->validate([
                'roles' => 'required|array|min:1',
            ], [
                'roles.required' => 'Please select at least one role when approving a user.',
            ]);
        }
        
        $oldStatus = $user->status;
        $newStatus = $validated['status'];

        $user->status = $newStatus;

        if ($newStatus === 'approved') {
            $user->approved_by = auth()->id();
            $user->approved_at = now();

            $user->rejected_by = null;
            $user->rejected_at = null;
            $user->rejection_reason = null;

            // assign roles only when approved
            $roleNames = Role::whereIn('id', $validated['roles'] ?? [])
                ->pluck('name')
                ->toArray();

            $user->syncRoles($roleNames);

        } elseif ($newStatus === 'rejected') {
            $user->rejected_by = auth()->id();
            $user->rejected_at = now();
            $user->rejection_reason = $validated['rejection_reason'] ?? null;

            // recommended: clear roles if rejected
            $user->syncRoles([]);

        } else { // pending
            // recommended: clear roles while pending
            $user->syncRoles([]);
        }

        $user->save();

        // clear Spatie cache after role changes
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Notify user only if status changed (queued if notification implements ShouldQueue)
        if ($oldStatus !== $newStatus) {
            $user->notify(new UserApprovalStatusNotification($newStatus));
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'User status updated successfully.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'required|string|max:15',
            'country' => 'required|string|max:100',
            'cnic_number' => 'required|string|max:20',
            'password' => 'nullable|confirmed|min:8',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->update($request->except('password'));

        if ($request->password) {
            $user->password = Hash::make($request->password);
            $user->save();
        }

        // Update user roles
        if ($request->has('roles')) {
            $user->syncRoles($request->input('roles'));
        }

        return redirect()->route('users.index')->with('status', 'User updated successfully.');

        // return back();
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index');
    }
}
