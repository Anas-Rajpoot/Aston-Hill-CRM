<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AeDomainRule implements ValidationRule
{
    private const FORBIDDEN_KEYWORDS = ['lac', 'rac', 'rat', 'sgns'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if ($value === '') {
            return;
        }

        if (str_contains($value, ' ')) {
            $fail('The domain must not contain spaces.');
            return;
        }

        if (preg_match('/[0-9]/', $value)) {
            $fail('The domain must not contain numbers (0–9).');
            return;
        }

        if (preg_match('/[@#$%^&*()\-+={}\[\]:;\'"\\\\<>,\?\/!_`|]/', $value)) {
            $fail('The domain must not contain special characters such as: @ # $ % ^ & * ( ) - + = { } [ ] : ; \' " \\ <> , ? / ! _ ` |');
            return;
        }

        $lower = strtolower($value);
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                $fail('The domain must not contain these keywords (case-insensitive): LAC, RAC, RAT, SGNS.');
                return;
            }
        }

        if (substr_count($value, '.') !== 1) {
            $fail('The domain must contain only one dot (.).');
            return;
        }

        if (! str_ends_with($lower, '.ae')) {
            $fail('The domain must end with .ae (example: example.ae).');
        }
    }
}
