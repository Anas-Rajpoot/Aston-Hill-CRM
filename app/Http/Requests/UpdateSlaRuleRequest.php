<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->hasRole('superadmin') || $user->can('manage-sla'));
    }

    public function rules(): array
    {
        return [
            'sla_duration_minutes'      => ['required', 'integer', 'min:1', 'max:10080'],
            'warning_threshold_minutes' => ['required', 'integer', 'min:0', 'lt:sla_duration_minutes'],
            'notification_email'        => ['required', 'email', 'max:255'],
            'is_active'                 => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'warning_threshold_minutes.lt' => 'Warning threshold must be less than SLA duration.',
            'sla_duration_minutes.max'     => 'SLA duration cannot exceed 7 days (10,080 minutes).',
        ];
    }
}
