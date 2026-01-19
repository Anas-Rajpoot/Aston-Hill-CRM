@extends('layouts.app')

@section('content')
<div style="max-width:420px;margin:40px auto;padding:20px;border:1px solid #ddd;border-radius:10px;">
  <h2>Enable Two-Factor Authentication</h2>
  <p>Scan the QR with Google Authenticator or Microsoft Authenticator:</p>

  <div style="margin:15px 0;">
    <img
      src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($qrCodeUrl) }}"
      alt="QR Code"
    />
  </div>

  <form method="POST" action="{{ route('2fa.enable') }}">
    @csrf
    <label>Enter 6-digit OTP</label>
    <input name="otp" maxlength="6" style="width:100%;padding:10px;margin-top:6px;" />
    @error('otp') <div style="color:red;margin-top:8px;">{{ $message }}</div> @enderror

    <button style="margin-top:15px;width:100%;padding:10px;">Enable</button>
  </form>
</div>
@endsection
