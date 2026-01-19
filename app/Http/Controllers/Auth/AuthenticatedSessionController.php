<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Otp;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Services\SmsService;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (!Auth::attempt($request->only('email','password'))) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials'
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        
        if ($user->hasRole('superadmin')) {
            $request->session()->put('2fa_passed', true);
            return redirect('super-admin/dashboard');
        }

        if ($user->status !== 'approved') {
            Auth::logout();

            return redirect()->route('login')->with(
                'status',
                'Your registration is completed. Please wait for super admin approval.'
            );
        }

        // If user has 2FA enabled, force OTP
        if ($user->two_factor_enabled) {
            $request->session()->forget('2fa_passed'); // ensure fresh verification
            return redirect()->route('2fa.verify.form');
        }

        // If user does NOT have 2FA enabled, optionally send them to setup page:
        // return redirect()->route('2fa.setup');

        // Normal login
        $request->session()->put('2fa_passed', true);
        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget('2fa_passed');
        
        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
