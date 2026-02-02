<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Crypt;

class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();

        // Optional: superadmin doesn't need 2FA setup
        if ($user->hasRole('superadmin')) {
            return redirect()->route('super-admin.dashboard');
        }

        $google2fa = new Google2FA();

        if (!$user->two_factor_secret) {
            $user->two_factor_secret = Crypt::encryptString($google2fa->generateSecretKey());
            $user->save();
        }

        $appName = config('app.name', 'Aston Hill CRM');
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $appName,
            $user->email,
            $user->two_factor_secret
        );

        return view('auth.2fa-setup', compact('qrCodeUrl'));
    }

    public function enable(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        $google2fa = new Google2FA();

        if (!$user->two_factor_secret) {
            return back()->withErrors(['otp' => '2FA secret missing. Please open setup again.']);
        }

        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->otp);

        if (!$valid) {
            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.']);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        // Mark as verified for this session so user doesn't get redirected again
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard'))
            ->with('success', '2FA enabled successfully.');
    }

    public function verifyForm()
    {
        return view('auth.2fa-verify');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (!$user || !$user->two_factor_enabled) {
            $request->session()->put('2fa_passed', true);
            return $request->expectsJson()
                ? response()->json(['redirect' => '/'])
                : redirect()->intended(route('dashboard'));
        }

        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($user->two_factor_secret, $request->otp);

        if (!$valid) {
            if ($request->expectsJson()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['otp' => ['Invalid OTP.']]);
            }
            return back()->withErrors(['otp' => 'Invalid OTP.']);
        }

        $request->session()->put('2fa_passed', true);

        return $request->expectsJson()
            ? response()->json(['redirect' => '/'])
            : redirect()->intended(route('dashboard'));
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->two_factor_secret = null;
        $user->save();

        $request->session()->forget('2fa_passed');

        return back()->with('success', '2FA disabled.');
    }
}
