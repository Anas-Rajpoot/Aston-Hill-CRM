<x-app-layout>
    <div class="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-semibold mb-2">Two-Factor Verification</h2>
        <p class="text-sm text-gray-600 mb-4">Enter the 6-digit code from your Authenticator app.</p>

        <form method="POST" action="{{ route('2fa.verify') }}">
            @csrf

            <label class="block text-sm font-medium mb-1">OTP Code</label>
            <input name="otp" maxlength="6"
                   class="w-full border rounded-md px-3 py-2" />

            @error('otp')
                <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
            @enderror

            <button class="mt-4 w-full bg-indigo-600 text-white py-2 rounded-md">
                Verify
            </button>
        </form>
    </div>
</x-app-layout>
