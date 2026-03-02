<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SecuritySettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // Session Management
            'auto_logout_after_minutes'  => $this->auto_logout_after_minutes,
            'session_warning_minutes'    => $this->session_warning_minutes,
            'force_logout_on_close'      => (bool) $this->force_logout_on_close,
            'prevent_multiple_sessions'  => (bool) $this->prevent_multiple_sessions,

            // Login & Account Security
            'max_login_attempts'               => $this->max_login_attempts,
            'lock_after_failed_attempts'       => (bool) $this->lock_after_failed_attempts,
            'lock_duration_minutes'            => $this->lock_duration_minutes,
            'force_password_reset_on_first_login' => (bool) $this->force_password_reset_on_first_login,

            // Password Policies
            'min_length'           => $this->min_length,
            'require_uppercase'    => (bool) $this->require_uppercase,
            'require_number'       => (bool) $this->require_number,
            'require_special'      => (bool) $this->require_special,

            // Meta
            'updated_at'      => $this->updated_at?->toIso8601String(),
            'updated_by_name' => $this->updater?->name,
        ];
    }
}
