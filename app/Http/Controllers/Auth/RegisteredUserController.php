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
use App\Models\Country;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $countries = Country::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'timezone']);
    
        return view('auth.register', compact('countries'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', new \App\Rules\MeetsPasswordPolicy],
            'phone' => ['required'],
            'country' => ['required'],
            'cnic_number' => ['required'],
        ]);

        // Set must_change_password if security settings require it for first login
        $mustChange = false;
        try {
            $mustChange = \App\Models\SecuritySetting::current()->force_password_reset_on_first_login;
        } catch (\Throwable $e) {}

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'country' => $request->country,
            'timezone' => $request->timezone ?? '',
            'cnic_number' => $request->cnic_number,
            'status' => 'pending',
            'two_factor_enabled' => 1,
            'must_change_password' => $mustChange,
            'password_changed_at' => now(),
        ]);

        User::role('superadmin')->each(function ($admin) use ($user) {
            $admin->notify(new NewUserApprovalNotification($user));
        });

        $message = 'Your registration is completed. Please wait for super admin approval.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => '/login']);
        }
        return redirect()->route('login')->with('status', $message)->with('success', $message);
    }
}
