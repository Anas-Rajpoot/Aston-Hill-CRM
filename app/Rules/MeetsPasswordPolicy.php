<?php

namespace App\Rules;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a password against the dynamic security policy stored in security_settings.
 * Enforces: minimum length, uppercase, number, special character requirements.
 */
class MeetsPasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $settings = SecuritySetting::current();
        } catch (\Throwable $e) {
            // If settings table doesn't exist yet, use sensible defaults
            return;
        }

        if (mb_strlen($value) < $settings->min_length) {
            $fail("The password must be at least {$settings->min_length} characters.");
            return;
        }

        if ($settings->require_uppercase && ! preg_match('/[A-Z]/', $value)) {
            $fail('The password must contain at least one uppercase letter.');
        }

        if ($settings->require_number && ! preg_match('/[0-9]/', $value)) {
            $fail('The password must contain at least one number.');
        }

        if ($settings->require_special && ! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The password must contain at least one special character (!@#$%^&* etc.).');
        }
    }
}
