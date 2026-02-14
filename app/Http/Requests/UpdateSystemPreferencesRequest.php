<?php

namespace App\Http\Requests;

use App\Models\SystemPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasRole('superadmin') || $user->can('manage-system-preferences'));
    }

    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:100', 'timezone:all'],
            'default_dashboard_landing_page' => ['required', 'string', Rule::in(SystemPreference::LANDING_PAGES)],
            'default_table_page_size' => ['required', 'integer', Rule::in(SystemPreference::PAGE_SIZES)],
            'auto_refresh_dashboard' => ['required', 'boolean'],
            'auto_refresh_interval_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'auto_save_draft_forms' => ['required', 'boolean'],
            'session_warning_before_logout' => ['required', 'boolean'],
            'session_warning_minutes' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'timezone.timezone' => 'Please select a valid IANA timezone.',
            'default_table_page_size.in' => 'Page size must be 10, 25, 50, or 100.',
            'auto_refresh_interval_minutes.min' => 'Refresh interval must be at least 1 minute.',
            'session_warning_minutes.min' => 'Warning time must be at least 1 minute.',
        ];
    }
}
