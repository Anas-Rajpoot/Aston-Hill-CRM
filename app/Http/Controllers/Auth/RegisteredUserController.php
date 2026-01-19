<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewUserApprovalNotification;


class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required'],
            'country' => ['required'],
            'cnic_number' => ['required'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone'=>$request->phone,
            'country'=>$request->country,
            'timezone'=>$request->timezone,
            'cnic_number'=>$request->cnic_number,
            'status'=>'pending',
            'two_factor_enabled'=>1
        ]);

        User::role('superadmin')->each(function ($admin) use ($user) {
            $admin->notify(new NewUserApprovalNotification($user));
        });

        return redirect()->route('login')
        ->with('status', 'Your registration is completed. Please wait for super admin approval.')
        ->with('success', 'Your registration is completed. Please wait for super admin approval.');
    }
}
