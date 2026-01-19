<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Otp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    public function verify(Request $request, $userId)
    {
        $request->validate(['otp' => 'required|digits:6']);
        $key = 'otp-attempts:' . $userId;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'otp' => 'Too many attempts. Try again later.',
            ]);
        }
        RateLimiter::hit($key, 300);

        $otp = Otp::where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$otp || !Hash::check($request->otp, $otp->otp)) {
            return back()->withErrors(['Invalid or expired OTP']);
        }

        RateLimiter::clear($key);
        
        Auth::loginUsingId($userId);

        $otp->delete();

        session(['otp_verified'=>true]);
        
        return redirect()->route('dashboard');
    }

}
