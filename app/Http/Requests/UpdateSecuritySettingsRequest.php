<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->hasRole('superadmin') || $u->can('manage-security-settings'));
    }

    public function rules(): array
    {
        return [
            // Session Management
            'auto_logout_after_minutes'  => 'required|integer|min:1|in:10,15,30,45,60,120,240,480,720,1440',
            'session_warning_minutes'    => 'required|integer|in:1,3,5,10,15|lte:auto_logout_after_minutes',
            'force_logout_on_close'      => 'boolean',
            'prevent_multiple_sessions'  => 'boolean',

            // Login & Account Security
            'max_login_attempts'               => 'required|integer|min:1|max:20',
            'lock_after_failed_attempts'       => 'boolean',
            'lock_duration_minutes'            => 'required_if:lock_after_failed_attempts,true|integer|min:1|max:1440',
            'force_password_reset_on_first_login' => 'boolean',

            // Password Policies
            'min_length'        => 'required|integer|min:6|max:128',
            'require_uppercase' => 'boolean',
            'require_number'    => 'boolean',
            'require_special'   => 'boolean',
            'password_expiry_days' => 'required|integer|min:0|max:3650',
        ];
    }

    public function messages(): array
    {
        return [
            'session_warning_minutes.lte'      => 'Warning time must be less than or equal to auto-logout time.',
            'lock_duration_minutes.required_if' => 'Lock duration is required when account locking is enabled.',
            'min_length.min'                    => 'Password length must be at least 6 characters.',
        ];
    }
}
