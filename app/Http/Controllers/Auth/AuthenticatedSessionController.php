<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Models\Otp;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;
use App\Services\SmsService;
use App\Models\UserLoginLog;

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
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        if (!Auth::attempt($request->only('email','password'))) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $request->session()->regenerate();

        UserLoginLog::where('user_id', auth()->id())
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(1)
            ->update(['session_id' => $request->session()->getId()]);

        $user = Auth::user();

        if ($request->filled('redirect') && str_starts_with($request->input('redirect'), config('app.url'))) {
            $request->session()->put('url.intended', $request->input('redirect'));
        }

        if ($user->hasRole('superadmin')) {
            $request->session()->put('2fa_passed', true);
            return $this->loginResponse($request, '/');
        }

        if ($user->status !== 'approved') {
            Auth::logout();
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'email' => ['Your registration is completed. Please wait for super admin approval.'],
                ]);
            }
            return redirect()->route('login')->with('status', 'Your registration is completed. Please wait for super admin approval.');
        }

        if ($user->two_factor_enabled) {
            $request->session()->forget('2fa_passed');
            return $this->loginResponse($request, '/2fa/verify');
        }

        $request->session()->put('2fa_passed', true);
        return $this->loginResponse($request, '/');
    }

    private function loginResponse(Request $request, string $redirect): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirect]);
        }
        return redirect()->intended($redirect === '/2fa/verify' ? route('2fa.verify.form') : route('dashboard'));
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
